<?php

namespace Tests\Unit;

use App\Services\Risk\ActivityEventService;
use App\Services\Risk\RiskLedgerService;
use App\Services\Risk\RiskReleaseEngine;
use App\Services\Risk\RiskReleaseQuery;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RiskActivityAndReleaseQueryTest extends TestCase
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
    }

    public function test_activity_uses_uuid_for_device_and_server_install_identity(): void
    {
        $engine = \Mockery::mock(RiskReleaseEngine::class);
        $engine->shouldNotReceive('evaluateDevice');
        $request = Request::create('/api/risk/activity/batch', 'POST', ['events' => [[
            'event_id' => 'e-1', 'session_id' => 's-1', 'occurred_at' => time(),
            'install_id' => 'unrelated-client-value',
        ]]], [], [], ['HTTP_APP_ID' => '10027', 'HTTP_UUID' => 'uuid-a', 'HTTP_DEVICE_SN' => 'install-a']);
        $result = (new ActivityEventService())->ingest($request);
        $this->assertSame(1, $result['accepted']);
        $row = DB::table('risk_activity_events')->first();
        $this->assertSame('uuid-a', $row->device_identity);
        $this->assertSame('install-a', $row->install_id);
    }

    public function test_activity_falls_back_to_device_sn_when_uuid_is_missing(): void
    {
        $engine = \Mockery::mock(RiskReleaseEngine::class);
        $engine->shouldNotReceive('evaluateDevice');
        $request = Request::create('/api/risk/activity/batch', 'POST', ['events' => [[
            'event_id' => 'e-2', 'session_id' => 's-1', 'occurred_at' => time(),
        ]]], [], [], ['HTTP_APP_ID' => '10027', 'HTTP_DEVICE_SN' => 'install-b']);
        $this->assertSame(1, (new ActivityEventService())->ingest($request)['accepted']);
        $this->assertSame('install-b', DB::table('risk_activity_events')->value('device_identity'));
    }

    public function test_online_evidence_rejects_excessive_clock_skew_in_both_directions(): void
    {
        $occurred = Carbon::parse('2026-10-29 10:00:00', 'Asia/Shanghai');
        $id = DB::table('risk_activity_events')->insertGetId([
            'event_id' => 'skew-event', 'app_id' => 10027, 'device_identity' => 'uuid-a',
            'session_id' => 's-1', 'occurred_at' => $occurred->toDateTimeString(),
            'received_at' => $occurred->copy()->subMinutes(5)->toDateTimeString(),
            'interaction' => 1, 'event_seq' => 1,
        ]);
        Carbon::setTestNow($occurred);
        DB::table('risk_activity_events')->where('id', $id)->update(['online_verified' => 1]);
        $engine = new RiskReleaseEngine(app(RiskLedgerService::class));
        $method = new \ReflectionMethod($engine, 'onlineInteraction');
        $scope = (object) ['app_id' => 10027, 'device_identity' => 'uuid-a'];
        $this->assertFalse($method->invoke($engine, $scope, $occurred->getTimestamp()));
        DB::table('risk_activity_events')->where('id', $id)->update(['received_at' => $occurred->copy()->addMinutes(5)->toDateTimeString()]);
        $this->assertFalse($method->invoke($engine, $scope, $occurred->getTimestamp()));
        DB::table('risk_activity_events')->where('id', $id)->update(['received_at' => $occurred->copy()->addSeconds(120)->toDateTimeString()]);
        $this->assertTrue($method->invoke($engine, $scope, $occurred->getTimestamp()));
        Carbon::setTestNow();
    }

    public function test_release_list_batches_relations_and_matches_details_including_latest_revocation(): void
    {
        foreach (range(1, 5) as $id) {
            DB::table('risk_releases')->insert([
                'id' => $id, 'ip' => '198.51.100.'.$id, 'released_at' => '2026-10-29 10:00:00',
                'rule_id' => 'continuous-use-release', 'rule_version' => '1', 'idempotency_key' => 'release-'.$id,
            ]);
            if ($id === 5) {
                continue; // 无关联记录也不能退回逐行查询。
            }
            DB::table('risk_release_scopes')->insert([
                'release_id' => $id, 'scope_id' => $id, 'app_id' => 10027, 'app_name' => '应用',
                'device_identity' => 'uuid-'.$id, 'reason_codes' => '["list_blacklist"]',
            ]);
            foreach (['restored', 'already_revoked'] as $result) {
                DB::table('risk_revocations')->insert([
                    'release_id' => $id, 'request_id' => $id.'-'.$result,
                    'result' => $result, 'revoked_at' => '2026-10-29 11:00:00',
                    'operator_id' => 1, 'operator_name' => 'admin',
                ]);
            }
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $service = new RiskReleaseQuery();
        $list = $service->list([], 1, 20);
        $this->assertCount(4, DB::getQueryLog()); // count + page + scopes + latest revocations
        DB::disableQueryLog();
        $this->assertSame(5, $list['count']);
        $this->assertSame(['5', '4', '3', '2', '1'], array_column($list['list'], 'id'));
        foreach ($list['list'] as $row) {
            $detail = $service->detail((int) $row['id']);
            foreach ($row as $key => $value) {
                $this->assertSame($value, $detail[$key], $key);
            }
        }
        $this->assertSame('already_revoked', $list['list'][1]['revocation']['result']);
        $this->assertSame([], $list['list'][0]['devices']);
    }
}
