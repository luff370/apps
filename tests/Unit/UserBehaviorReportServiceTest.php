<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\ValidationException;
use App\Support\Services\UserBehaviorReportService;

class UserBehaviorReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('user_behavior_reports');
        Schema::create('user_behavior_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('app_id');
            $table->string('uuid');
            $table->string('device_sn');
            $table->string('package_name');
            $table->string('platform');
            $table->string('app_version');
            $table->string('market_channel');
            $table->string('ip');
            $table->json('behavior');
            $table->json('device_environment')->nullable();
            $table->json('ad_extension')->nullable();
            $table->timestamp('client_reported_at')->nullable();
            $table->timestamps();
        });

        Request::macro('authUserId', fn() => 42);
    }

    public function test_it_records_behavior_and_optional_segments_without_device_env(): void
    {
        $request = Request::create('/api/user/behavior/report', 'POST', [
            'behavior' => [
                'touch_sample_count' => 12,
                'touch_timing_entropy' => 55,
                'click_sample_count' => 3,
                'unknown_field' => 'ignored',
            ],
            'device_environment' => ['is_emulator' => false, 'is_vpn' => false, 'env_allows_ads' => true],
            'ad_extension' => ['ad_switch' => 1],
            'reported_at' => 1754640000,
        ], [], [], [
            'HTTP_APP_ID' => '10048',
            'HTTP_PACKAGE_NAME' => 'com.example.app',
            'HTTP_PLATFORM' => 'android',
            'HTTP_DEVICE_SN' => 'device-002',
        ]);

        $report = (new UserBehaviorReportService())->record($request);

        $this->assertSame(42, $report->user_id);
        $this->assertSame('device-002', $report->device_sn);
        $this->assertSame(12, $report->behavior['touch_sample_count']);
        $this->assertArrayNotHasKey('unknown_field', $report->behavior);
        $this->assertFalse($report->device_environment['is_emulator']);
        $this->assertSame(1, $report->ad_extension['ad_switch']);
    }

    public function test_it_accepts_flat_behavior_payload(): void
    {
        $request = Request::create('/api/user/behavior/report', 'POST', [
            'swipe_sample_count' => 2,
            'swipe_linearity' => 92,
            'swipe_speed_uniformity' => 84,
        ]);

        $report = (new UserBehaviorReportService())->record($request);

        $this->assertSame(2, $report->behavior['swipe_sample_count']);
        $this->assertSame(92, $report->behavior['swipe_linearity']);
    }

    public function test_it_rejects_reports_without_samples(): void
    {
        $this->expectException(ValidationException::class);

        $request = Request::create('/api/user/behavior/report', 'POST', [
            'behavior' => ['sensor_static_score' => 80],
        ]);

        (new UserBehaviorReportService())->record($request);
    }
}
