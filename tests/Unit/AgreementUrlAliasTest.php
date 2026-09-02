<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Support\Services\AgreementUrlAliasService;

class AgreementUrlAliasTest extends TestCase
{
    public function test_alias_is_stable_per_app_package_and_type(): void
    {
        $service = new AgreementUrlAliasService();

        $first = $service->make(10036, 'com.example.app', 'privacy');
        $same = $service->make(10036, 'com.example.app', 'privacy');
        $otherType = $service->make(10036, 'com.example.app', 'user');
        $otherApp = $service->make(10037, 'com.example.app', 'privacy');
        $otherPackage = $service->make(10036, 'com.other.app', 'privacy');

        $this->assertSame($first, $same);
        $this->assertNotSame($first, $otherType);
        $this->assertNotSame($first, $otherApp);
        $this->assertNotSame($first, $otherPackage);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $first);
    }

    public function test_aliased_web_route_keeps_platform_and_alias(): void
    {
        $alias = (new AgreementUrlAliasService())->make(10036, 'com.example.app', 'privacy');
        $request = Request::create('/' . $alias . '/oppo', 'GET');
        $route = app('router')->getRoutes()->match($request);

        $this->assertSame(
            'App\Http\Controllers\Web\CommonController@appAgreementByAlias',
            $route->getAction('controller')
        );
        $this->assertSame($alias, $route->parameter('alias'));
        $this->assertSame('oppo', $route->parameter('platform'));
    }

    public function test_legacy_agreement_route_still_matches(): void
    {
        $request = Request::create('/agreement/privacy/10036/oppo', 'GET');
        $route = app('router')->getRoutes()->match($request);

        $this->assertSame(
            'App\Http\Controllers\Web\CommonController@appAgreement',
            $route->getAction('controller')
        );
        $this->assertSame('privacy', $route->parameter('type'));
        $this->assertSame('10036', $route->parameter('app_id'));
        $this->assertSame('oppo', $route->parameter('platform'));
    }
}
