<?php

namespace Tests\Unit;

use App\Models\UserWhitelistLog;
use App\Services\User\UserWhitelistService;
use App\Support\Utils\Token;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserWhitelistLogUserIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.testing_sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('testing_sqlite');
        config()->set('database.default', 'testing_sqlite');
        DB::setDefaultConnection('testing_sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('app_id')->default(0);
            $table->string('uuid')->default('');
            $table->unsignedTinyInteger('is_del')->default(0);
        });

        Schema::create('user_whitelist_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->default(0);
            $table->unsignedInteger('user_id')->default(0);
            $table->string('uuid');
            $table->string('platform');
            $table->string('market_channel');
            $table->string('version');
            $table->string('device');
            $table->string('ip');
            $table->string('region');
            $table->unsignedTinyInteger('type')->default(0);
            $table->string('source');
            $table->string('source_type');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_log_uses_token_user_id_when_request_has_a_valid_token(): void
    {
        DB::table('users')->insert([
            'id' => 18,
            'app_id' => 10001,
            'uuid' => 'device-uuid',
            'is_del' => 0,
        ]);
        Token::generate(['user_id' => 42, 'login_way' => 'account', 'is_reg' => 1], 'token-42');
        $this->bindRequest('token-42', 'device-uuid');

        $log = $this->writeLog(10001, 'device-uuid');

        $this->assertSame(42, $log->user_id);
    }

    public function test_log_falls_back_to_user_matched_by_app_and_uuid(): void
    {
        DB::table('users')->insert([
            ['id' => 7, 'app_id' => 10001, 'uuid' => 'device-uuid', 'is_del' => 1],
            ['id' => 9, 'app_id' => 10001, 'uuid' => 'device-uuid', 'is_del' => 0],
            ['id' => 11, 'app_id' => 10002, 'uuid' => 'device-uuid', 'is_del' => 0],
        ]);
        $this->bindRequest('', 'device-uuid');

        $log = $this->writeLog(10001, 'device-uuid');

        $this->assertSame(9, $log->user_id);
    }

    public function test_log_keeps_zero_when_user_cannot_be_resolved(): void
    {
        $this->bindRequest('', '');

        $log = $this->writeLog(10001, '');

        $this->assertSame(0, $log->user_id);
    }

    private function bindRequest(string $token, string $uuid): void
    {
        $this->app->instance('request', Request::create('/api/app/info', 'POST', [], [], [], [
            'HTTP_TOKEN' => $token,
            'HTTP_UUID' => $uuid,
        ]));
    }

    private function writeLog(int $appId, string $uuid): UserWhitelistLog
    {
        return UserWhitelistService::whitelistUserAccessLog([
            'app_id' => $appId,
            'uuid' => $uuid,
            'platform' => 'android',
            'market_channel' => 'huawei',
            'version' => '1.0.0',
            'device' => 'device-1',
            'ip' => '10.0.0.5',
            'region' => '广东省深圳市',
            'type' => 1,
            'source' => 'device-1',
            'source_type' => 'device',
        ]);
    }
}
