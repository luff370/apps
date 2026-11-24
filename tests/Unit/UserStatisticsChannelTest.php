<?php

namespace Tests\Unit;

use App\Services\Statistics\UserStatisticsService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserStatisticsChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        foreach (['users', 'user_uuids', 'user_statistics'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->integer('app_id');
                $table->string('market_channel')->default('');
                if ($name === 'users') {
                    $table->integer('reg_time');
                    $table->string('uuid')->default('');
                    $table->boolean('is_del')->default(false);
                    $table->boolean('is_reg')->default(false);
                    $table->string('device_sn')->default('');
                } elseif ($name === 'user_uuids') {
                    $table->string('uuid')->default('');
                    $table->string('version')->default('');
                    $table->timestamps();
                } else {
                    $table->date('date');
                    $table->integer('active_users_count');
                }
            });
        }
        $connection = \Mockery::mock();
        $connection->shouldReceive('client')->andReturn(\Mockery::mock());
        Redis::shouldReceive('connection')->andReturn($connection);
    }

    public function test_channels_merge_aliases_and_exclude_other_apps_dates_and_total_rows(): void
    {
        foreach ([['yyb', 10], ['yingyongbao', 20], ['huawei', 5], ['', 999]] as [$channel, $active]) {
            DB::table('user_statistics')->insert([
                'app_id' => 1, 'market_channel' => $channel,
                'date' => '2026-10-20', 'active_users_count' => $active,
            ]);
        }
        foreach ([['yyb', 1, '2026-10-20'], ['yingyongba', 1, '2026-10-20'],
            ['', 1, '2026-10-20'], ['oppo', 2, '2026-10-20'], ['vivo', 1, '2026-10-19']] as [$channel, $appId, $date]) {
            DB::table('user_uuids')->insert([
                'app_id' => $appId, 'market_channel' => $channel,
                'created_at' => "$date 12:00:00", 'updated_at' => "$date 12:00:00",
            ]);
        }
        foreach (['yingyongbao', 'xiaomi'] as $channel) {
            DB::table('users')->insert([
                'app_id' => 1, 'market_channel' => $channel,
                'reg_time' => Carbon::parse('2026-10-20 12:00:00')->timestamp,
            ]);
        }
        $rows = (new UserStatisticsService())->reportByChannel([
            'app_id' => 1, 'market_channel' => 'huawei',
            'start_date' => '2026-10-20', 'end_date' => '2026-10-20',
        ]);
        $this->assertCount(4, $rows);
        $this->assertSame([
            'market_channel' => 'yingyongbao', 'channel_name' => '应用宝',
            'active_users' => 30, 'new_users' => 2, 'registered_users' => 1, 'register_rate' => 50.0,
        ], $rows[0]);
        $byChannel = array_column($rows, null, 'market_channel');
        $this->assertSame(0, $byChannel['']['active_users']);
        $this->assertSame('未知渠道', $byChannel['']['channel_name']);
        $this->assertSame(1, $byChannel['xiaomi']['registered_users']);
        $this->assertEquals(0, $byChannel['xiaomi']['register_rate']);
        $this->assertSame(5, $byChannel['huawei']['active_users']);
        $this->assertSame([], (new UserStatisticsService())->reportByChannel(['app_id' => 2]));
        $this->assertSame([], (new UserStatisticsService())->reportByChannel([]));
    }

    public function test_devices_use_first_uuid_rows_and_enrich_without_cross_app_matches(): void
    {
        Schema::create('system_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('user_access_log', function (Blueprint $table) {
            $table->id();
            $table->integer('app_id');
            $table->string('uuid');
            $table->string('device');
            $table->string('os');
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_24_000003_add_user_device_list_indexes.php'))->up();

        DB::table('system_apps')->insert([['id' => 1, 'name' => '应用甲'], ['id' => 2, 'name' => '应用乙']]);
        DB::table('user_uuids')->insert([
            ['id' => 1, 'app_id' => 1, 'uuid' => 'shared-uuid', 'market_channel' => 'yyb', 'version' => '1.0', 'created_at' => '2026-11-24 00:00:00'],
            ['id' => 2, 'app_id' => 1, 'uuid' => 'guest-uuid', 'market_channel' => 'yingyongba', 'version' => '1.1', 'created_at' => '2026-11-24 23:59:59'],
            ['id' => 3, 'app_id' => 2, 'uuid' => 'shared-uuid', 'market_channel' => 'yingyongbao', 'version' => '8.0', 'created_at' => '2026-11-24 12:00:00'],
            ['id' => 4, 'app_id' => 1, 'uuid' => 'old-uuid', 'market_channel' => 'yyb', 'version' => '0.9', 'created_at' => '2026-11-23 23:59:59'],
        ]);
        DB::table('users')->insert([
            ['id' => 10, 'app_id' => 1, 'uuid' => 'shared-uuid', 'device_sn' => 'stored_code', 'is_reg' => 1, 'is_del' => 0, 'reg_time' => 0],
            ['id' => 11, 'app_id' => 1, 'uuid' => 'shared-uuid', 'device_sn' => 'deleted_code', 'is_reg' => 1, 'is_del' => 1, 'reg_time' => 0],
            ['id' => 12, 'app_id' => 1, 'uuid' => 'guest-uuid', 'device_sn' => 'guest-uuid', 'is_reg' => 0, 'is_del' => 0, 'reg_time' => 0],
        ]);
        DB::table('user_access_log')->insert([
            ['app_id' => 1, 'uuid' => 'shared-uuid', 'device' => 'install_old', 'os' => 'Android 14', 'created_at' => '2026-11-24 10:00:00'],
            ['app_id' => 1, 'uuid' => 'shared-uuid', 'device' => 'install_new', 'os' => 'Android 15', 'created_at' => '2026-11-25 10:00:00'],
            ['app_id' => 2, 'uuid' => 'shared-uuid', 'device' => 'other-app', 'os' => 'iOS', 'created_at' => '2026-11-25 11:00:00'],
        ]);

        $service = app(UserStatisticsService::class);
        $filters = ['app_id' => 1, 'market_channel' => 'yingyongbao', 'start_date' => '2026-11-24', 'end_date' => '2026-11-24'];
        $firstPage = $service->reportDevices($filters + ['limit' => 1]);
        $this->assertSame($service->reportBasic($filters)['new_users'], $firstPage['count']);
        $this->assertSame(2, $firstPage['count']);
        $this->assertSame('guest-uuid', $firstPage['list'][0]['uuid']);
        $this->assertNull($firstPage['list'][0]['user_id']);
        $this->assertSame('guest-uuid', $firstPage['list'][0]['install_id']);
        $this->assertSame('2026-11-24 23:59:59', $firstPage['list'][0]['last_seen_at']);

        $device = $service->reportDevices($filters + ['limit' => 1, 'page' => 2])['list'][0];
        $this->assertSame('10', $device['user_id']);
        $this->assertSame('应用甲', $device['app_name']);
        $this->assertSame('yyb', $device['market_channel']);
        $this->assertSame('应用宝', $device['channel_name']);
        $this->assertSame('1.0', $device['version']);
        $this->assertSame('stored_code', $device['install_id']);
        $this->assertSame('Android 15', $device['os_version']);
        $this->assertSame('2026-11-25 10:00:00', $device['last_seen_at']);
        $this->assertSame(1, $service->reportDevices($filters + ['registered' => '0'])['count']);
        $this->assertSame(1, $service->reportDevices($filters + ['registered' => '1', 'keyword' => '10'])['count']);
        $this->assertSame(1, $service->reportDevices($filters + ['keyword' => 'stored_code'])['count']);
        $this->assertSame(0, $service->reportDevices($filters + ['keyword' => 'install_old'])['count']);
        $this->assertSame(0, $service->reportDevices($filters + ['keyword' => 'other-app'])['count']);
        $this->assertSame(0, $service->reportDevices($filters + ['keyword' => '%'])['count']);
        $this->assertSame(3, $service->reportDevices(array_diff_key($filters, ['app_id' => true]))['count']);
    }

}
