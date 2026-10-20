<?php

namespace Tests\Unit;

use App\Services\Statistics\OperationStatisticsService;
use App\Services\Statistics\UserStatisticsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class RechargeVersionUserStatsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('user_access_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->default(0);
            $table->unsignedInteger('app_id')->default(0);
            $table->string('market_channel', 32)->default('');
            $table->string('version', 32)->default('');
            $table->string('uuid', 64)->default('');
            $table->timestamps();
        });

        Schema::create('user_uuids', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id');
            $table->string('uuid', 64);
            $table->string('market_channel', 32)->default('');
            $table->string('version', 32)->default('');
            $table->timestamps();
            $table->unique(['app_id', 'uuid']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->default(0);
            $table->string('uuid', 64)->default('');
            $table->string('market_channel', 32)->default('');
            $table->string('app_version', 32)->default('');
            $table->unsignedInteger('reg_time')->default(0);
        });

        Schema::create('user_statistics', function (Blueprint $table) {
            $table->unsignedInteger('app_id');
            $table->string('market_channel', 32)->default('');
            $table->date('date');
            $table->unsignedInteger('active_users_count')->default(0);
        });
    }

    public function test_version_filter_counts_active_new_and_registered_users(): void
    {
        DB::table('user_access_log')->insert([
            $this->accessLog('uuid-a', '1.0.0', 'huawei', '2026-10-18 09:00:00'),
            $this->accessLog('uuid-a', '1.0.0', 'huawei', '2026-10-18 18:00:00'),
            $this->accessLog('uuid-b', '1.0.0', 'huawei', '2026-10-18 10:00:00'),
            $this->accessLog('uuid-c', '1.1.0', 'huawei', '2026-10-18 11:00:00'),
            $this->accessLog('uuid-a', '1.0.0', 'huawei', '2026-10-19 09:00:00'),
            $this->accessLog('uuid-d', '1.0.0', 'xiaomi', '2026-10-19 09:00:00'),
            $this->accessLog('', '1.0.0', 'huawei', '2026-10-19 12:00:00'),
        ]);
        DB::table('user_uuids')->insert([
            $this->uuidRow('uuid-a', '1.0.0', 'huawei', '2026-10-18 09:00:00'),
            $this->uuidRow('uuid-c', '1.1.0', 'huawei', '2026-10-18 11:00:00'),
            $this->uuidRow('uuid-old', '', 'huawei', '2026-10-18 12:00:00'),
        ]);
        DB::table('users')->insert([
            $this->userRow('uuid-a', '1.0.0', 'huawei', '2026-10-18 09:00:00'),
            $this->userRow('uuid-c', '1.1.0', 'huawei', '2026-10-18 11:00:00'),
            $this->userRow('uuid-old', '', 'huawei', '2026-10-18 12:00:00'),
        ]);
        DB::table('user_statistics')->insert([
            'app_id' => 10001,
            'market_channel' => '',
            'date' => '2026-10-18',
            'active_users_count' => 99,
        ]);

        $service = new OperationStatisticsService();
        $filter = [
            'app_id' => 10001,
            'market_channel' => 'huawei',
            'version' => '1.0.0',
        ];

        $this->assertSame(3, $this->invoke($service, 'activeUsers', [
            Carbon::parse('2026-10-18'),
            Carbon::parse('2026-10-19'),
            $filter,
        ]));
        $this->assertSame(1, $this->invoke($service, 'newUuidUsers', [
            Carbon::parse('2026-10-18'),
            Carbon::parse('2026-10-19'),
            $filter,
        ]));
        $this->assertSame(1, $this->invoke($service, 'newUsers', [
            Carbon::parse('2026-10-18'),
            Carbon::parse('2026-10-19'),
            $filter,
        ]));
        $this->assertSame(99, $this->invoke($service, 'activeUsers', [
            Carbon::parse('2026-10-18'),
            Carbon::parse('2026-10-18'),
            ['app_id' => 10001],
        ]));
    }

    public function test_first_uuid_keeps_the_version_from_the_first_visit(): void
    {
        $redis = \Mockery::mock();
        $redis->shouldReceive('sAdd')->andReturn(1);
        $redis->shouldReceive('expire')->andReturn(true);
        $connection = \Mockery::mock();
        $connection->shouldReceive('client')->andReturn($redis);
        Redis::shouldReceive('connection')->andReturn($connection);

        $service = new UserStatisticsService();
        $service->recordAppInfoVisit('uuid-a', 10001, 'huawei', '1.0.0');
        $service->recordAppInfoVisit('uuid-a', 10001, 'huawei', '1.2.0');

        $this->assertSame('1.0.0', DB::table('user_uuids')->where('uuid', 'uuid-a')->value('version'));
        $this->assertSame(1, DB::table('user_uuids')->count());
    }

    private function invoke(OperationStatisticsService $service, string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod($service, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($service, ...$args);
    }

    private function accessLog(string $uuid, string $version, string $channel, string $createdAt): array
    {
        return [
            'user_id' => 0,
            'app_id' => 10001,
            'market_channel' => $channel,
            'version' => $version,
            'uuid' => $uuid,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function uuidRow(string $uuid, string $version, string $channel, string $createdAt): array
    {
        return [
            'app_id' => 10001,
            'uuid' => $uuid,
            'market_channel' => $channel,
            'version' => $version,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    private function userRow(string $uuid, string $version, string $channel, string $createdAt): array
    {
        return [
            'app_id' => 10001,
            'uuid' => $uuid,
            'market_channel' => $channel,
            'app_version' => $version,
            'reg_time' => Carbon::parse($createdAt)->timestamp,
        ];
    }
}
