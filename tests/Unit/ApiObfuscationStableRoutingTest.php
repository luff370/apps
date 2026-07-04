<?php

namespace Tests\Unit;

use ReflectionClass;
use Tests\TestCase;
use Illuminate\Http\Request;
use App\Services\App\AppApiObfuscationService;
use App\Http\Controllers\Api\ObfuscatedGatewayController;

class ApiObfuscationStableRoutingTest extends TestCase
{
    public function test_url_alias_is_stable_per_app_and_url(): void
    {
        $service = $this->newService();
        $makeAlias = $this->method(AppApiObfuscationService::class, 'makeAlias');

        $used = [];
        $first = $makeAlias->invokeArgs($service, [
            ['app_id' => 10048, 'package_name' => 'com.he.dahu'],
            'POST',
            '/app/info',
            &$used,
        ]);

        $used = [];
        $same = $makeAlias->invokeArgs($service, [
            ['app_id' => 10048, 'package_name' => 'com.he.dahu'],
            'POST',
            'app//info/',
            &$used,
        ]);

        $used = [];
        $otherApp = $makeAlias->invokeArgs($service, [
            ['app_id' => 10049, 'package_name' => 'com.he.dahu'],
            'POST',
            '/app/info',
            &$used,
        ]);

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $otherApp);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $first);
    }

    public function test_gateway_prefix_is_stable_and_checked_against_profile(): void
    {
        $service = $this->newService();
        $gatewayPrefix = $this->method(AppApiObfuscationService::class, 'gatewayPrefixForProfile');

        $profile = ['app_id' => 10048, 'package_name' => 'com.he.dahu'];
        $first = $gatewayPrefix->invoke($service, $profile);
        $same = $gatewayPrefix->invoke($service, $profile);
        $otherApp = $gatewayPrefix->invoke($service, ['app_id' => 10049, 'package_name' => 'com.he.dahu']);

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $otherApp);
        $this->assertMatchesRegularExpression('#^/api/[a-z]+/[a-z]{8,32}/$#', $first);

        $prefixPath = trim(str_replace('/api/', '', $first), '/');
        $request = Request::create('/api/' . $prefixPath . '/abc', 'POST');
        $controller = (new ReflectionClass(ObfuscatedGatewayController::class))->newInstanceWithoutConstructor();
        $isAllowed = $this->method(ObfuscatedGatewayController::class, 'isAllowedGatewayPrefix');

        $this->assertTrue($isAllowed->invoke($controller, $request, $profile));
        $this->assertTrue($isAllowed->invoke($controller, Request::create('/api/open/abc', 'POST'), $profile));
        $this->assertFalse($isAllowed->invoke($controller, Request::create('/api/open/wrongsuffix/abc', 'POST'), $profile));
        $this->assertFalse($isAllowed->invoke($controller, Request::create('/api/unknownprefix/abc', 'POST'), $profile));
    }

    public function test_dynamic_route_entry_keeps_alias_parameter_in_the_right_position(): void
    {
        $controller = $this->getMockBuilder(ObfuscatedGatewayController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['dispatch'])
            ->getMock();

        $request = Request::create('/api/open/atlasriver/alias123', 'POST');
        $controller->expects($this->once())
            ->method('dispatch')
            ->with($request, 'alias123')
            ->willReturn('ok');

        $this->assertSame('ok', $controller->dispatchDynamic($request, 'atlasriver', 'alias123'));
    }

    public function test_new_three_segment_url_matches_dynamic_route_not_legacy(): void
    {
        $request = Request::create('/api/open/atlasriver/abc12345', 'POST');
        $route = app('router')->getRoutes()->match($request);

        $this->assertSame(
            'App\Http\Controllers\Api\ObfuscatedGatewayController@dispatchDynamic',
            $route->getAction('controller')
        );
        $this->assertSame('abc12345', $route->parameter('alias'));
        $this->assertSame('atlasriver', $route->parameter('gatewaySuffix'));
    }

    public function test_legacy_two_segment_url_matches_dispatch_route(): void
    {
        $request = Request::create('/api/open/abc12345', 'POST');
        $route = app('router')->getRoutes()->match($request);

        $this->assertSame(
            'App\Http\Controllers\Api\ObfuscatedGatewayController@dispatch',
            $route->getAction('controller')
        );
        $this->assertSame('abc12345', $route->parameter('alias'));
    }

    private function newService(): AppApiObfuscationService
    {
        return (new ReflectionClass(AppApiObfuscationService::class))->newInstanceWithoutConstructor();
    }

    private function method(string $class, string $method): \ReflectionMethod
    {
        $reflection = new ReflectionClass($class);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method;
    }
}
