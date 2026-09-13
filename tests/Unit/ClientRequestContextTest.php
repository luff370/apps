<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Support\Services\ClientRequestContext;

class ClientRequestContextTest extends TestCase
{
    public function test_it_prefers_headers_over_device_env(): void
    {
        $request = Request::create('/api/app/info', 'POST', [], [], [], [
            'HTTP_UUID' => 'header-uuid',
            'HTTP_APP_VERSION' => '1.0.0',
            'HTTP_MARKET_CHANNEL' => 'Android',
            'HTTP_PLATFORM' => 'iOS',
            'HTTP_TOKEN' => 'header-token',
        ]);
        $request->attributes->set('device_env_risk', [
            'probe' => [
                'uuid' => 'probe-uuid',
                'app_version' => '2.0.0',
                'market_channel' => 'huawei',
                'platform' => 'android',
                'token' => 'probe-token',
            ],
        ]);

        $this->assertSame('header-uuid', ClientRequestContext::uuid($request));
        $this->assertSame('1.0.0', ClientRequestContext::appVersion($request));
        $this->assertSame('android', ClientRequestContext::marketChannel($request));
        $this->assertSame('ios', ClientRequestContext::platform($request));
        $this->assertSame('header-token', ClientRequestContext::token($request));
    }

    public function test_it_falls_back_to_device_env_and_package_map(): void
    {
        config(['app_package_map' => ['com.dingsheng.nspp' => 10063]]);

        $request = Request::create('/api/app/info', 'POST', [], [], [], [
            'HTTP_PACKAGE_NAME' => 'com.dingsheng.nspp',
        ]);
        $request->attributes->set('device_env_risk', [
            'probe' => [
                'uuid' => 'env-uuid',
                'os_version' => '14',
                'device_sn' => 'sn-1',
                'platform' => 'Android',
                'token' => 'env-token',
                'app_version' => '1.2.0',
            ],
        ]);

        $this->assertSame('10063', ClientRequestContext::appId($request));
        $this->assertSame('env-uuid', ClientRequestContext::uuid($request));
        $this->assertSame('14', ClientRequestContext::osVersion($request));
        $this->assertSame('sn-1', ClientRequestContext::deviceSn($request));
        $this->assertSame('android', ClientRequestContext::platform($request));
        $this->assertSame('env-token', ClientRequestContext::token($request));
        $this->assertSame('1.2.0', ClientRequestContext::appVersion($request));
    }
}
