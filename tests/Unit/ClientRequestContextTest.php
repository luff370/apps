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
        ]);
        $request->attributes->set('device_env_risk', [
            'probe' => [
                'uuid' => 'probe-uuid',
                'app_version' => '2.0.0',
                'market_channel' => 'huawei',
            ],
        ]);

        $this->assertSame('header-uuid', ClientRequestContext::uuid($request));
        $this->assertSame('1.0.0', ClientRequestContext::appVersion($request));
        $this->assertSame('android', ClientRequestContext::marketChannel($request));
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
            ],
        ]);

        $this->assertSame('10063', ClientRequestContext::appId($request));
        $this->assertSame('env-uuid', ClientRequestContext::uuid($request));
        $this->assertSame('14', ClientRequestContext::osVersion($request));
        $this->assertSame('sn-1', ClientRequestContext::deviceSn($request));
    }
}
