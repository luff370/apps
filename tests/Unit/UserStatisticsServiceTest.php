<?php

namespace Tests\Unit;

use App\Services\Statistics\UserStatisticsService;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class UserStatisticsServiceTest extends TestCase
{
    private $redisClient;
    private UserStatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisClient = Mockery::mock();
        $connection = Mockery::mock();
        $connection->shouldReceive('client')->andReturn($this->redisClient);
        Redis::shouldReceive('connection')->andReturn($connection);

        $this->service = new UserStatisticsService();
    }

    public function test_active_stat_key_keeps_legacy_format_for_app_total(): void
    {
        $this->assertSame(
            'user_active_stat:10001-2026-08-30',
            $this->service->getUserActiveStatKey(10001, '2026-08-30')
        );
    }

    public function test_active_stat_key_includes_market_channel(): void
    {
        $this->assertSame(
            'user_active_stat:10001:huawei-2026-08-30',
            $this->service->getUserActiveStatKey(10001, '2026-08-30', 'Huawei')
        );
    }

    public function test_user_active_stat_writes_app_total_and_channel_sets(): void
    {
        $this->redisClient->shouldReceive('sAdd')
            ->once()
            ->with($this->service->getUserActiveStatKey(10001), 'uuid-1')
            ->andReturn(1);
        $this->redisClient->shouldReceive('sAdd')
            ->once()
            ->with($this->service->getUserActiveStatKey(10001, null, 'huawei'), 'uuid-1')
            ->andReturn(1);
        $this->redisClient->shouldReceive('sAdd')
            ->once()
            ->with($this->service->getUserActiveChannelIndexKey(10001), 'huawei')
            ->andReturn(1);

        $this->service->userActiveStat('uuid-1', 10001, 'huawei');
    }
}
