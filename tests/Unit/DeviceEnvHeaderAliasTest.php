<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Support\Services\DeviceEnvHeaderAliasService;

class DeviceEnvHeaderAliasTest extends TestCase
{
    public function test_alias_is_stable_per_app_and_package(): void
    {
        $service = new DeviceEnvHeaderAliasService();

        $first = $service->make(10063, 'com.dingsheng.nspp');
        $same = $service->make(10063, 'com.dingsheng.nspp');
        $otherApp = $service->make(10048, 'com.dingsheng.nspp');
        $otherPackage = $service->make(10063, 'com.other.app');

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $otherApp);
        $this->assertNotSame($first, $otherPackage);
        $this->assertMatchesRegularExpression('/^[A-Z][a-z0-9]{7}$/', $first);
    }
}
