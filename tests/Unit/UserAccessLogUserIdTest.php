<?php

namespace Tests\Unit;

use App\Jobs\RecordAccessLog;
use App\Support\Utils\Token;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserAccessLogUserIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.testing_sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('testing_sqlite');
        config()->set('database.default', 'testing_sqlite');
        DB::setDefaultConnection('testing_sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('app_id');
            $table->string('uuid');
            $table->integer('is_del')->default(0);
            $table->integer('last_time')->default(0);
            $table->string('last_ip')->default('');
            $table->integer('login_count')->default(0);
        });
        Schema::create('user_access_log', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('app_id');
            foreach (['market_channel', 'version', 'os', 'uuid', 'device', 'ip', 'region', 'source', 'return_data'] as $field) {
                $table->text($field);
            }
            $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 7, 'app_id' => 10027, 'uuid' => 'device-uuid', 'is_del' => 0],
            ['id' => 9, 'app_id' => 10027, 'uuid' => 'device-uuid', 'is_del' => 0],
            ['id' => 11, 'app_id' => 10028, 'uuid' => 'device-uuid', 'is_del' => 0],
            ['id' => 12, 'app_id' => 10027, 'uuid' => 'device-uuid', 'is_del' => 1],
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
    }

    public function test_token_has_priority_without_user_select(): void
    {
        Token::generate(['user_id' => 42], 'token-42');
        $this->writeLog(0, 'device-uuid', 'token-42');
        $this->assertSame(42, DB::table('user_access_log')->value('user_id'));
        $this->assertCount(0, $this->userSelects());
    }

    public function test_missing_token_uses_latest_active_user_in_same_app(): void
    {
        $this->writeLog();
        $this->assertSame(9, DB::table('user_access_log')->value('user_id'));
        $this->assertCount(1, $this->userSelects());
    }

    public function test_unknown_token_falls_back_to_uuid(): void
    {
        $this->writeLog(0, 'device-uuid', 'unknown-token');
        $this->assertSame(9, DB::table('user_access_log')->value('user_id'));
    }

    public function test_unmatched_uuid_keeps_zero_and_still_writes_log(): void
    {
        $this->writeLog(0, 'unmatched');
        $this->assertSame(0, DB::table('user_access_log')->value('user_id'));
    }

    public function test_explicit_user_id_is_preserved_without_lookup(): void
    {
        $this->writeLog(27);
        $this->assertSame(27, DB::table('user_access_log')->value('user_id'));
        $this->assertCount(0, $this->userSelects());
    }

    public function test_cache_failure_falls_back_to_uuid(): void
    {
        Cache::shouldReceive('get')->once()->with('api-token:broken-token')->andThrow(new \RuntimeException('cache unavailable'));
        $this->writeLog(0, 'device-uuid', 'broken-token');
        $this->assertSame(9, DB::table('user_access_log')->value('user_id'));
    }

    public function test_empty_uuid_still_skips_log_and_user_queries(): void
    {
        $this->writeLog(0, '');
        $this->assertSame(0, DB::table('user_access_log')->count());
        $this->assertCount(0, $this->userSelects());
    }

    private function writeLog(int $uid = 0, string $uuid = 'device-uuid', ?string $token = null): void
    {
        (new RecordAccessLog($uid, 10027, 'huawei', '2.6.2', 'Android', $uuid,
            'device-uuid', '127.0.0.1', 'api/app/info', [], $token))->handle();
    }

    private function userSelects(): array
    {
        return array_values(array_filter(DB::getQueryLog(), static fn ($query) =>
            str_starts_with($query['query'], 'select') && str_contains($query['query'], '"users"')));
    }
}
