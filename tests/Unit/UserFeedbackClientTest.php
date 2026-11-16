<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\UserController;
use App\Models\UserFeedback;
use App\Support\Utils\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserFeedbackClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.feedback_testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        config()->set('database.default', 'feedback_testing');
        DB::setDefaultConnection('feedback_testing');
        foreach ([
            '2024_02_21_013645_create_user_feedback_table.php',
            '2025_01_02_024816_add_app_id_to_user_feedback_table.php',
            '2025_02_18_015104_add_field_to_user_feedback_table.php',
            '2026_11_16_120000_add_device_context_to_user_feedback_table.php',
        ] as $file) {
            (require database_path('migrations/' . $file))->up();
        }
    }

    public function test_submission_captures_device_and_ip_and_ignores_spoofed_owner_and_reply(): void
    {
        $request = $this->request('device-a');
        $request->merge($this->payload() + [
            'user_id' => 99, 'uuid' => 'spoofed', 'ip' => 'spoofed',
            'status' => 1, 'recover_content' => 'spoofed', 'admin_name' => 'spoofed',
        ]);
        app(UserController::class)->feedback($request);

        $row = UserFeedback::query()->firstOrFail();
        $this->assertSame(0, $row->user_id);
        $this->assertSame('device-a', $row->uuid);
        $this->assertSame('sn-a', $row->device);
        $this->assertSame('android', $row->platform);
        $this->assertSame('14', $row->os_version);
        $this->assertSame('203.0.113.8', $row->ip);
        $this->assertSame(0, $row->status);
        $this->assertSame('', $row->recover_content);
        $this->assertSame('', $row->admin_name);
    }

    public function test_device_env_submission_uses_existing_context_fallback(): void
    {
        $request = $this->request('');
        foreach (['Uuid', 'Device-Sn', 'Platform', 'OS-Version'] as $header) {
            $request->headers->remove($header);
        }
        $request->attributes->set('device_env_risk', ['probe' => [
            'uuid' => 'env-device', 'device_sn' => 'env-sn', 'platform' => 'iOS', 'os_version' => '18',
        ]]);
        $request->merge($this->payload());
        app(UserController::class)->feedback($request);
        $row = UserFeedback::query()->firstOrFail();
        $this->assertSame('env-device', $row->uuid);
        $this->assertSame('env-sn', $row->device);
        $this->assertSame('ios', $row->platform);
        $this->assertSame('18', $row->os_version);
    }

    public function test_logged_in_submission_is_visible_to_the_account_on_another_device(): void
    {
        Token::generate(['user_id' => 7], 'feedback-submit-token');
        $request = $this->request('device-a', 'feedback-submit-token');
        $request->merge($this->payload());
        app(UserController::class)->feedback($request);
        $row = UserFeedback::query()->firstOrFail();
        $this->assertSame(7, $row->user_id);
        $this->assertSame([$row->id], array_column($this->list('device-b', 'feedback-submit-token'), 'id'));
        $this->assertSame([], $this->list('device-a'));
    }

    public function test_anonymous_user_sees_only_this_devices_anonymous_feedback_and_reply(): void
    {
        $own = $this->seedFeedback(0, 'device-a', 10027, '管理员回复');
        $this->seedFeedback(0, 'device-b');
        $this->seedFeedback(7, 'device-a');
        $this->seedFeedback(0, 'device-a', 10028);
        $this->seedFeedback(0, '');
        $rows = $this->list('device-a');
        $this->assertSame([$own->id], array_column($rows, 'id'));
        $this->assertSame('管理员回复', $rows[0]['recover_content']);
        $this->assertSame(1, $rows[0]['status']);
        $this->assertArrayNotHasKey('ip', $rows[0]);
        $this->assertArrayNotHasKey('uuid', $rows[0]);
    }

    public function test_login_includes_account_feedback_and_current_device_anonymous_feedback(): void
    {
        $anonymous = $this->seedFeedback(0, 'device-a');
        $account = $this->seedFeedback(7, 'device-b');
        $this->seedFeedback(8, 'device-a');
        $this->seedFeedback(0, 'device-b');
        $this->seedFeedback(7, 'device-a', 10028);
        Token::generate(['user_id' => 7], 'feedback-test-token');
        $this->assertSame([$anonymous->id, $account->id], array_column($this->list('device-a', 'feedback-test-token'), 'id'));
        $this->assertSame([$account->id], array_column($this->list('', 'feedback-test-token'), 'id'));
        // 登录不会改变匿名记录归属，退出登录后本设备仍可查看原反馈。
        $this->assertSame([$anonymous->id], array_column($this->list('device-a'), 'id'));
    }

    public function test_missing_identity_never_returns_all_anonymous_feedback(): void
    {
        $this->seedFeedback(0, '');
        $this->seedFeedback(0, 'device-a');
        $this->assertSame([], $this->list(''));
        $this->assertSame([], $this->list('', 'invalid-token'));
    }

    public function test_list_route_does_not_require_login(): void
    {
        $route = app('router')->getRoutes()->match(Request::create('/api/user/feedback/list', 'POST'));
        $this->assertNotContains('token_auth', $route->gatherMiddleware());
        $this->assertContains('device_env_risk', $route->gatherMiddleware());
    }

    public function test_device_context_migration_can_roll_back(): void
    {
        (require database_path('migrations/2026_11_16_120000_add_device_context_to_user_feedback_table.php'))->down();
        $this->assertFalse(Schema::hasColumn('user_feedback', 'uuid'));
        $this->assertTrue(Schema::hasColumn('user_feedback', 'content'));
    }

    private function request(string $uuid, string $token = ''): Request
    {
        $request = Request::create('/api/user/feedback', 'POST', [], [], [], [
            'HTTP_APP_ID' => '10027', 'HTTP_UUID' => $uuid, 'HTTP_TOKEN' => $token,
            'HTTP_APP_VERSION' => '1.2.0', 'HTTP_MARKET_CHANNEL' => 'huawei',
            'HTTP_DEVICE_SN' => 'sn-a', 'HTTP_PLATFORM' => 'Android', 'HTTP_OS_VERSION' => '14',
            'REMOTE_ADDR' => '203.0.113.8',
        ]);
        app()->instance('request', $request);

        return $request;
    }

    private function payload(): array
    {
        return ['type' => '建议', 'content' => '测试反馈内容', 'images' => [], 'email' => '', 'phone' => ''];
    }

    private function seedFeedback(int $userId, string $uuid, int $appId = 10027, string $reply = ''): UserFeedback
    {
        return UserFeedback::query()->create($this->payload() + [
            'app_id' => $appId, 'user_id' => $userId, 'uuid' => $uuid,
            'status' => $reply === '' ? 0 : 1, 'recover_content' => $reply,
        ]);
    }

    private function list(string $uuid, string $token = ''): array
    {
        $this->request($uuid, $token);

        return app(UserController::class)->feedbackList()->getData(true)['data'];
    }
}
