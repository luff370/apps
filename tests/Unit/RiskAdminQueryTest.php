<?php

namespace Tests\Unit;

use App\Dao\Risk\RiskProbeLogDao;
use App\Models\RiskProbeLog;
use App\Services\Risk\RiskAdminService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskAdminQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 独立内存库，不执行项目迁移，也不触碰开发库。
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('risk_probe_logs', function (Blueprint $table) {
            $table->id();
            foreach (RiskProbeLog::adminListColumns() as $column) {
                if ($column !== 'id') {
                    $table->string($column)->nullable();
                }
            }
            $table->string('device_identity')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('app_id');
            $table->string('uuid');
            $table->string('account');
            $table->integer('is_del')->default(0);
        });
        Schema::create('system_apps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('is_del')->default(0);
        });
        foreach ([
            [1, 1, 'a', 90, 1, 0, 'ok'],
            [2, 1, 'a', 30, 0, 1, 'ok'],
            [3, 2, 'a', 100, 1, 0, 'ok'],
            [4, 1, 'b', 29, 0, 1, 'ok'],
            [5, 1, 'c', 70, 0, 0, 'ok'],
            [6, 1, 'd', 90, 1, 0, 'ok'],
            [7, 1, 'd', 99, 1, 0, 'error'],
            [8, 1, '', 99, 1, 0, 'ok'],
            [9, 1, null, 99, 1, 0, 'ok'],
            [10, 1, 'e', 90, 1, 0, 'ok'],
        ] as [$id, $app, $device, $score, $compliance, $ads, $status]) {
            DB::table('risk_probe_logs')->insert([
                'id' => $id, 'app_id' => $app, 'device_identity' => $device,
                'risk_score' => $score, 'compliance_mode' => $compliance, 'ad_switch' => $ads,
                'status' => $status, 'created_at' => '2026-10-29 12:00:00',
            ]);
        }
    }

    public function test_snapshot_preserves_app_filter_latest_rows_and_score_boundaries(): void
    {
        $service = app(RiskAdminService::class);
        $snapshot = $this->invoke($service, 'deviceSnapshot', [['app_id' => 1]]);
        $this->assertEqualsCanonicalizing([2, 4, 5, 6, 10], array_column($snapshot, 'id'));
        $stats = $this->invoke($service, 'statsFromSnapshot', [$snapshot]);
        $this->assertSame([
            'device_total' => 5, 'high_risk' => 3, 'score_normal' => 1, 'score_watch' => 1,
            'score_high' => 1, 'score_critical' => 2, 'decision_pass' => 1,
            'decision_verify' => 1, 'decision_limit' => 1, 'decision_block' => 2,
        ], (array) $stats);
        $top = $this->invoke($service, 'topRiskDevices', [$snapshot]);
        $this->assertSame([10, 6, 5, 2, 4], $top->pluck('id')->all());
        // 同一 service 实例先后查询不同应用，不能复用上次快照。
        $this->assertSame([3], array_column($this->invoke($service, 'deviceSnapshot', [['app_id' => 2]]), 'id'));
    }

    public function test_filters_are_applied_before_latest_not_after(): void
    {
        $dao = app(RiskProbeLogDao::class);
        $ids = $dao->latestDeviceIds(['app_id' => 1, 'decision' => 'block'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([1, 6, 10], $ids);
        $snapshot = $this->invoke(app(RiskAdminService::class), 'deviceSnapshot', [['app_id' => 1, 'risk_level' => 'high']]);
        $this->assertEqualsCanonicalizing([1, 5, 6, 10], array_column($snapshot, 'id'));
        $this->assertEqualsCanonicalizing([3, 4, 5, 6, 10], $dao->latestDeviceIds([])->pluck('id')->all());
    }

    public function test_empty_snapshot_and_top_are_empty(): void
    {
        $service = app(RiskAdminService::class);
        $snapshot = $this->invoke($service, 'deviceSnapshot', [['app_id' => 99]]);
        $this->assertSame([], $snapshot);
        $this->assertSame(0, $this->invoke($service, 'statsFromSnapshot', [$snapshot])->device_total);
        $this->assertTrue($this->invoke($service, 'topRiskDevices', [$snapshot])->isEmpty());
    }

    public function test_clusters_and_event_accounts_do_not_leak_across_apps(): void
    {
        DB::table('risk_probe_logs')->whereIn('id', [1, 2, 3])->update(['user_uuid' => 'a']);
        DB::table('users')->insert([
            ['id' => 11, 'app_id' => 1, 'uuid' => 'a', 'account' => 'app-one-a'],
            ['id' => 12, 'app_id' => 1, 'uuid' => 'a', 'account' => 'app-one-b'],
            ['id' => 99, 'app_id' => 2, 'uuid' => 'a', 'account' => 'app-two'],
        ]);
        $service = app(RiskAdminService::class);
        $clusters = $service->clusters(['app_id' => 1, 'cluster_type' => 'multi_account']);
        $this->assertSame(1, $clusters['count']);
        $this->assertSame(2, $clusters['list'][0]['device_id']);
        $this->assertSame(30, $clusters['list'][0]['risk_score']);
        $this->assertSame(['app-one-b', 'app-one-a'], $clusters['list'][0]['sample_accounts']);
        $events = $service->eventList(['keyword' => 'a']);
        $users = array_column($events['list'], 'user_id', 'id');
        $this->assertSame(12, $users[2]);
        $this->assertSame(99, $users[3]);
    }

    private function invoke(object $service, string $method, array $args)
    {
        return (new \ReflectionMethod($service, $method))->invokeArgs($service, $args);
    }
}
