<?php

namespace Tests\Unit;

use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\RiskDossierService;
use App\Services\Risk\RiskLedgerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskQueryOptimizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        (require database_path('migrations/2026_10_28_120000_create_risk_ledger_tables.php'))->up();
        (require database_path('migrations/2026_10_30_160000_complete_risk_integration.php'))->up();
        (require database_path('migrations/2026_10_30_170000_isolate_historical_risk_scopes.php'))->up();
        (require database_path('migrations/2026_10_30_180000_async_risk_activity_projection.php'))->up();
        Schema::create('system_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('is_del')->default(0);
        });
    }

    public function test_risk_read_routes_resolve_to_existing_controllers(): void
    {
        // Production routes use require_once; explicitly reload the risk group in each isolated test app.
        app('router')->group(['prefix' => 'admin', 'namespace' => 'App\\Http\\Controllers\\Admin'], base_path('routes/admin/risk.php'));
        foreach ([
            '/admin/risk/event/count' => 'eventCount',
            '/admin/risk/event' => 'events',
            '/admin/risk/ip-dossiers/options' => 'dossierOptions',
            '/admin/risk/ip-dossiers/example/relations' => 'relations',
            '/admin/risk/publishing-monitor' => 'publishingBoard',
        ] as $path => $method) {
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path, 'GET'));
            $this->assertSame($method, $route->getActionMethod());
            $this->assertTrue(method_exists($route->getControllerClass(), $method));
        }
    }

    private function service(): RiskDossierService
    {
        $ledger = \Mockery::mock(RiskLedgerService::class)->makePartial();
        $ledger->shouldReceive('caughtUp')->andReturn(true);

        return new RiskDossierService($ledger);
    }

    private function dossier(string $ip, string $state = 'high'): void
    {
        DB::table('risk_ip_dossiers')->insert([
            'ip' => $ip, 'state' => $state, 'device_count' => 10000, 'account_count' => 8000,
            'apps' => '[{"id":"1","name":"One"}]', 'tags' => '[{"code": "root", "active": true}]',
            'collected_at' => '2026-10-01 00:00:00', 'last_activity_at' => '2026-10-29 00:00:00',
        ]);
    }

    public function test_compact_dossiers_keep_counts_without_placeholder_arrays_or_options_queries(): void
    {
        $this->dossier('192.0.2.1');
        $this->dossier('192.0.2.10', 'watch');
        DB::enableQueryLog();
        $result = $this->service()->list(['compact' => true, 'include_options' => false], 1, 20);
        $this->assertSame(2, $result['count']);
        $this->assertSame(['all' => 2, 'high' => 1, 'watch' => 1, 'released' => 0], $result['state_counts']);
        $this->assertSame([], $result['list'][0]['devices']);
        $this->assertSame(10000, $result['list'][0]['device_count']);
        $this->assertNull($result['options']);
        $this->assertCount(2, DB::getQueryLog());
        $this->assertSame(1, $this->service()->list(['keyword' => '192.0.2.1', 'compact' => true, 'include_options' => false], 1, 20)['count']);
        $this->assertCount(10000, $this->service()->list(['include_options' => false], 1, 1)['list'][0]['devices']);
    }

    public function test_dossier_activity_dates_filter_full_days_counts_states_and_pages(): void
    {
        foreach ([
            ['192.0.2.1', 'high', '2026-11-04 23:59:59'],
            ['192.0.2.2', 'high', '2026-11-05 00:00:00'],
            ['192.0.2.3', 'watch', '2026-11-05 23:59:59.999999'],
            ['192.0.2.4', 'released', '2026-11-06 12:00:00'],
            ['192.0.2.5', 'high', '2026-11-06 23:59:59.999999'],
            ['192.0.2.6', 'high', '2026-11-07 00:00:00'],
            ['192.0.2.7', 'watch', null],
        ] as [$ip, $state, $at]) {
            $this->dossier($ip, $state);
            DB::table('risk_ip_dossiers')->where('ip', $ip)->update(['last_activity_at' => $at]);
        }
        $service = $this->service();
        $filter = ['start_date' => '2026-11-05', 'end_date' => '2026-11-06', 'compact' => true, 'include_options' => false];
        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $service->list($filter, 1, 2);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame(4, $first['count']);
        $this->assertSame(['all' => 4, 'high' => 2, 'watch' => 1, 'released' => 1], $first['state_counts']);
        $this->assertSame(['192.0.2.5', '192.0.2.4'], array_column($first['list'], 'ip'));
        $this->assertCount(2, $queries);
        foreach ($queries as $query) {
            $this->assertStringContainsString('"last_activity_at" >= ?', $query['query']);
            $this->assertStringContainsString('"last_activity_at" < ?', $query['query']);
            $this->assertSame(['2026-11-05 00:00:00', '2026-11-07 00:00:00'], array_slice($query['bindings'], 0, 2));
        }
        $second = $service->list($filter, 2, 2);
        $this->assertSame(['192.0.2.3', '192.0.2.2'], array_column($second['list'], 'ip'));
        $high = $service->list($filter + ['state' => 'high'], 1, 20);
        $this->assertSame(2, $high['count']);
        $this->assertSame($first['state_counts'], $high['state_counts']);
        $singleDay = $service->list(array_replace($filter, ['end_date' => '2026-11-05']), 1, 20);
        $this->assertSame(['192.0.2.3', '192.0.2.2'], array_column($singleDay['list'], 'ip'));
        $this->assertSame(5, $service->list(array_replace($filter, ['end_date' => '']), 1, 20)['count']);
        $this->assertSame(5, $service->list(array_replace($filter, ['start_date' => '']), 1, 20)['count']);
        $this->assertSame(7, $service->list(array_replace($filter, ['start_date' => '', 'end_date' => '']), 1, 20)['count']);
    }

    public function test_dossier_activity_dates_reject_invalid_or_reversed_ranges_before_querying(): void
    {
        foreach ([
            ['start_date' => '2026-02-30'],
            ['end_date' => '2026/11/05'],
            ['start_date' => ['2026-11-05']],
            ['start_date' => '2026-11-06', 'end_date' => '2026-11-05'],
        ] as $filter) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $this->service()->list($filter, 1, 20);
                $this->fail('Expected invalid activity dates to be rejected');
            } catch (\App\Exceptions\AdminException $exception) {
                $this->assertStringContainsString('最近活动', $exception->getMessage());
                $this->assertSame([], DB::getQueryLog());
            } finally {
                DB::disableQueryLog();
            }
        }
    }

    public function test_dossier_controller_passes_frontend_activity_date_parameters(): void
    {
        $this->app->instance('request', \Illuminate\Http\Request::create('/admin/risk/ip-dossiers', 'GET', [
            'start_date' => '2026-11-01', 'end_date' => '2026-11-05', 'page' => 3, 'limit' => 20,
        ]));
        $dossiers = \Mockery::mock(RiskDossierService::class);
        $dossiers->shouldReceive('list')->once()->withArgs(fn ($filter, $page, $limit) => $filter['start_date'] === '2026-11-01' && $filter['end_date'] === '2026-11-05' && $page === 3 && $limit === 20
        )->andReturn(['list' => [], 'count' => 0]);
        $controller = new \App\Http\Controllers\Admin\Risk\RiskLedgerController(
            \Mockery::mock(\App\Services\Risk\RiskProbabilityService::class), $dossiers,
            \Mockery::mock(\App\Services\Risk\RiskReleaseQuery::class),
            \Mockery::mock(\App\Services\Risk\RiskReleaseEngine::class),
            \Mockery::mock(\App\Services\Risk\MonitorAccountService::class),
            \Mockery::mock(\App\Services\Risk\PublishingMonitorService::class),
        );
        $this->assertSame(0, $controller->dossiers()->getData(true)['data']['count']);
    }

    public function test_relations_paginate_grouped_rows_in_sql_and_load_only_requested_kind(): void
    {
        $ip = '192.0.2.1';
        $this->dossier($ip);
        foreach (range(1, 25) as $n) {
            foreach ([1, 2] as $appId) {
                DB::table('risk_scopes')->insert([
                    'ip' => $ip, 'app_id' => $appId, 'identity_kind' => 'device', 'device_identity' => sprintf('device-%02d', $n),
                    'user_id' => 'same-account', 'collected_at' => '2026-10-01 00:00:00',
                    'updated_at' => '2026-10-29 12:00:00',
                ]);
            }
        }
        $id = IpNormalizer::idFor($ip);
        DB::enableQueryLog();
        $page = $this->service()->relations($id, 'devices', 2, 20);
        $this->assertSame(25, $page['count']);
        $this->assertCount(5, $page['list']);
        $this->assertSame('device-21', $page['list'][0]['key']);
        $this->assertSame(2, $page['list'][0]['apps']);
        $this->assertFalse($page['has_more']);
        $queries = DB::getQueryLog();
        $this->assertCount(5, $queries);
        $this->assertStringContainsString('limit 20 offset 20', $queries[2]['query']);
        $this->assertSame(2, $page['list'][0]['account_count']);
        $this->assertSame(2, $this->service()->relations($id, 'accounts', 1, 20)['count']);
        $this->assertSame([], $this->service()->relations($id, 'devices', 9, 20)['list']);
        DB::flushQueryLog();
        $detail = $this->service()->detail($id, false);
        $this->assertArrayNotHasKey('scopes', $detail);
        $this->assertCount(1, DB::getQueryLog());
    }

    public function test_timeline_filters_categories_and_reason_with_pagination(): void
    {
        $ip = '192.0.2.1';
        $this->dossier($ip);
        foreach (['risk_hit', 'intake', 'reintake', 'login', 'auto_release', 'release_revoke'] as $type) {
            DB::table('risk_timeline')->insert(['ip' => $ip, 'event_type' => $type,
                'occurred_at' => '2026-10-29 12:00:00', 'reason_codes' => '["root"]']);
        }
        $service = $this->service();
        $id = IpNormalizer::idFor($ip);
        $page = $service->timeline($id, ['event_kind' => 'risk', 'reason_code' => 'root'], 2, 2);
        $this->assertSame(3, $page['count']);
        $this->assertCount(1, $page['list']);
        $this->assertSame(6, (int) $page['days'][0]->total);
        $this->assertSame(1, $service->timeline($id, ['event_kind' => 'activity'], 1, 20)['count']);
        $manual = $service->timeline($id, ['event_kind' => 'manual', 'include_days' => false], 1, 20);
        $this->assertSame(2, $manual['count']);
        $this->assertNull($manual['days']);
    }

    public function test_publishing_filters_before_limit_and_paginates_without_cross_app_identity_guesses(): void
    {
        foreach (['app_version_plans', 'app_version_plan_tasks'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $t->id();
                if ($name === 'app_version_plans') {
                    $t->integer('app_id');
                } else {
                    $t->integer('plan_id');
                    foreach (['version', 'market_channel', 'status', 'submitted_at', 'listed_at'] as $f) {
                        $t->string($f)->nullable();
                    }
                }
            });
        }
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-30 12:00:00', 'Asia/Shanghai'));
        foreach ([1, 2] as $id) {
            DB::table('app_version_plans')->insert(['id' => $id, 'app_id' => $id]);
            DB::table('app_version_plan_tasks')->insert(['id' => $id, 'plan_id' => $id, 'version' => '1.0', 'market_channel' => 'huawei', 'status' => '审核中', 'submitted_at' => '2026-10-29 09:00:00']);
        }
        DB::table('risk_monitor_accounts')->insert(['account' => 'test-account', 'app_id' => 1]);
        $base = ['app_id' => 1, 'device_identity' => 'device-a', 'version_name' => '1.0', 'market_channel' => 'huawei',
            'decision' => 'pass', 'event_type' => 'access', 'occurred_at' => '2026-10-29 12:00:00', 'received_at' => '2026-10-29 12:00:00'];
        DB::table('risk_access_facts')->insert([
            ['id' => 1, 'event_key' => 'risk', 'event_type' => 'guard', 'decision' => 'block', 'account' => null] + $base,
            ['id' => 2, 'event_key' => 'watched', 'account' => 'test-account'] + $base,
            ['id' => 3, 'event_key' => 'same-device', 'account' => null, 'app_id' => 2] + $base,
        ]);
        foreach (array_chunk(range(4, 5005), 100) as $chunk) {
            DB::table('risk_access_facts')->insert(array_map(fn ($id) => ['id' => $id, 'event_key' => 'quiet-'.$id] + $base, $chunk));
        }
        $query = app(\App\Services\Risk\PublishingQuery::class);
        $first = $query->page('2026-10-01', '2026-10-30', null, '', 1, 1, null, 'all', 5005);
        $this->assertSame(['2'], array_column($first['list'], 'id'));
        $this->assertSame(2, $first['count']);
        $this->assertTrue($first['has_more']);
        DB::table('risk_access_facts')->insert(['id' => 5006, 'event_key' => 'new', 'decision' => 'block', 'event_type' => 'guard'] + $base);
        $next = $query->page('2026-10-01', '2026-10-30', null, '', 2, 1, null, 'all', 5005);
        $this->assertSame(['1'], array_column($next['list'], 'id'));
        $this->assertFalse($next['has_more']);
        $aggregate = $query->aggregates('2026-10-01', '2026-10-30', null, '', 5005);
        $this->assertSame(1, (int) $aggregate[0]['test_count']);
        $this->assertSame(1, (int) $aggregate[0]['other_count']);
        \Carbon\Carbon::setTestNow();
    }
}
