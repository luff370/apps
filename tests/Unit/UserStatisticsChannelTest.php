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
                } elseif ($name === 'user_uuids') {
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
}
