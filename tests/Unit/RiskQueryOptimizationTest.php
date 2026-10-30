<?php

namespace Tests\Unit;

use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\PublishingMonitorService;
use App\Services\Risk\RiskDossierService;
use App\Services\Risk\RiskLedgerService;
use Carbon\Carbon;
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

    public function test_relations_paginate_grouped_rows_in_sql_and_load_only_requested_kind(): void
    {
        $ip = '192.0.2.1';
        $this->dossier($ip);
        foreach (range(1, 25) as $n) {
            foreach ([1, 2] as $appId) {
                DB::table('risk_scopes')->insert([
                    'ip' => $ip, 'app_id' => $appId, 'device_identity' => sprintf('device-%02d', $n),
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
        $this->assertCount(3, $queries);
        $this->assertStringContainsString('limit 20 offset 20', end($queries)['query']);
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

    public function test_publishing_filters_before_limit_and_paginates_without_cross_app_account_matches(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('app_id');
            $table->string('uuid');
            $table->string('phone')->default('');
            $table->string('account')->default('');
            $table->integer('is_del')->default(0);
        });
        Schema::create('risk_probe_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('app_id');
            foreach (['app_version', 'market_channel', 'client_ip', 'user_uuid', 'device_sn', 'status'] as $field) {
                $table->string($field)->default('');
            }
            foreach (['risk_score', 'compliance_mode', 'ad_switch'] as $field) {
                $table->integer($field)->nullable();
            }
            $table->dateTime('created_at');
        });
        DB::table('users')->insert(['app_id' => 1, 'uuid' => 'watched-uuid', 'account' => 'test-account']);
        $base = ['app_id' => 1, 'status' => 'ok', 'risk_score' => 0, 'compliance_mode' => 0, 'ad_switch' => 1,
            'created_at' => '2026-10-29 12:00:00'];
        DB::table('risk_probe_logs')->insert([
            ['id' => 1, 'user_uuid' => 'risky', 'risk_score' => 90] + $base,
            ['id' => 2, 'user_uuid' => 'watched-uuid'] + $base,
            ['id' => 3, 'app_id' => 2, 'user_uuid' => 'watched-uuid'] + $base,
        ]);
        foreach (array_chunk(range(4, 5005), 100) as $chunk) {
            DB::table('risk_probe_logs')->insert(array_map(fn ($id) => ['id' => $id, 'user_uuid' => 'quiet'] + $base, $chunk));
        }
        $method = new \ReflectionMethod(PublishingMonitorService::class, 'events');
        $service = app(PublishingMonitorService::class);
        $start = Carbon::parse('2026-10-01');
        $end = Carbon::parse('2026-10-30');
        $first = $method->invoke($service, [1, 2], ['test-account'], $start, $end, '', 1);
        $this->assertSame([2], array_column($first['list'], 'id'));
        $this->assertSame('test-account', $first['list'][0]['account']);
        $this->assertTrue($first['has_more']);
        DB::table('risk_probe_logs')->insert(['id' => 5006, 'user_uuid' => 'new', 'risk_score' => 90] + $base);
        $next = $method->invoke($service, [1, 2], ['test-account'], $start, $end, $first['next_cursor'], 1);
        $this->assertSame([1], array_column($next['list'], 'id'));
        $this->assertFalse($next['has_more']);
        $this->assertNull($next['next_cursor']);
    }
}
