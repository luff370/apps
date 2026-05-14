<?php

namespace Tests\Unit;

use App\Services\User\UserWhitelistService;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class UserWhitelistServiceTest extends TestCase
{
    private $redisClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisClient = Mockery::mock();
        $connection = Mockery::mock();
        $connection->shouldReceive('client')->andReturn($this->redisClient);
        Redis::shouldReceive('connection')->andReturn($connection);
    }

    public function test_device_whitelist_is_scoped_by_app_channel_and_version(): void
    {
        $this->redisClient->shouldReceive('hGet')
            ->with('user_whitelist:device', 'device-1')
            ->andReturn(json_encode([
                [
                    'app_id' => 10001,
                    'platform' => 'all',
                    'market_channel' => 'huawei',
                    'version' => '1.0.0',
                    'type' => 1,
                ],
                [
                    'app_id' => 10002,
                    'platform' => 'all',
                    'market_channel' => 'huawei',
                    'version' => '1.0.0',
                    'type' => 2,
                ],
            ]));

        $this->assertSame(1, UserWhitelistService::getByDevice('device-1', 10001, 'android', 'huawei', '1.0.0'));
        $this->assertSame(0, UserWhitelistService::getByDevice('device-1', 10001, 'android', 'xiaomi', '1.0.0'));
        $this->assertSame(0, UserWhitelistService::getByDevice('device-1', 10001, 'android', 'huawei', '2.0.0'));
        $this->assertSame(2, UserWhitelistService::getByDevice('device-1', 10002, 'android', 'huawei', '1.0.0'));
    }

    public function test_ip_whitelist_uses_exact_ip_then_wildcard_and_merges_matched_types(): void
    {
        $this->redisClient->shouldReceive('hGet')
            ->with('user_whitelist:ip', '10.0.0.5')
            ->andReturn(null);
        $this->redisClient->shouldReceive('hGet')
            ->with('user_whitelist:ip', '10.0.0.*')
            ->andReturn(json_encode([
                [
                    'app_id' => 10001,
                    'platform' => 'android',
                    'market_channel' => 'all',
                    'version' => '',
                    'type' => 1,
                ],
                [
                    'app_id' => 10001,
                    'platform' => 'android',
                    'market_channel' => 'huawei',
                    'version' => '1.0.0',
                    'type' => 2,
                ],
                [
                    'app_id' => 10002,
                    'platform' => 'android',
                    'market_channel' => 'huawei',
                    'version' => '1.0.0',
                    'type' => 2,
                ],
            ]));

        [$sourceIp, $type] = UserWhitelistService::getByIp('10.0.0.5', 10001, 'android', 'huawei', '1.0.0');

        $this->assertSame('10.0.0.*', $sourceIp);
        $this->assertSame(3, $type);
    }

    public function test_region_whitelist_decodes_cached_json_before_matching(): void
    {
        $this->redisClient->shouldReceive('hGet')
            ->with('user_whitelist:region', '广东省深圳市')
            ->andReturn(json_encode([
                [
                    'app_id' => 10002,
                    'platform' => 'all',
                    'market_channel' => 'all',
                    'type' => 1,
                ],
                [
                    'app_id' => 10001,
                    'platform' => 'android',
                    'market_channel' => 'huawei',
                    'type' => 2,
                ],
            ]));

        $this->assertSame(2, UserWhitelistService::getByRegion('广东省深圳市', 10001, 'android', 'huawei'));
        $this->assertSame(0, UserWhitelistService::getByRegion('广东省深圳市', 10001, 'ios', 'huawei'));
    }
}
