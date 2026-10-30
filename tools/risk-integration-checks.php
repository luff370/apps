<?php

// Audit reproductions: all database writes below are confined to SQLite :memory:.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\RiskProbeLog;
use App\Services\Risk\ActivityEventService;
use App\Services\Risk\RiskLedgerService;
use App\Services\Risk\RiskReleaseEngine;
use App\Services\Risk\RiskReleaseQuery;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
DB::purge('sqlite');
if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('Audit requires SQLite :memory:');
}
(require database_path('migrations/2026_10_28_120000_create_risk_ledger_tables.php'))->up();
(require database_path('migrations/2026_10_30_160000_complete_risk_integration.php'))->up();
(require database_path('migrations/2026_10_30_170000_isolate_historical_risk_scopes.php'))->up();
        (require database_path('migrations/2026_10_30_180000_async_risk_activity_projection.php'))->up();
Schema::create('risk_probe_logs', function (Blueprint $t) {
    $t->id();
    foreach (array_unique(array_merge(RiskProbeLog::adminListColumns(), ['route', 'touch_sample_count', 'click_sample_count', 'swipe_sample_count', 'device_identity'])) as $name) {
        if ($name !== 'id') {
            $t->string($name)->nullable();
        }
    }
});
Schema::create('system_apps', function (Blueprint $t) {
    $t->id();
    $t->string('name');
    $t->integer('is_del')->default(0);
});
Schema::create('users', function (Blueprint $t) {
    $t->id();
    $t->integer('app_id');
    $t->string('uuid');
    $t->string('phone')->nullable();
    $t->string('account')->nullable();
    $t->integer('is_del')->default(0);
});
Schema::create('risk_lists', function (Blueprint $t) {
    $t->id();
    $t->integer('app_id');
    $t->integer('status');
    foreach (['decision', 'target_type', 'target_value'] as $f) {
        $t->string($f)->nullable();
    }
});
Schema::create('app_version_plans', function (Blueprint $t) {
    $t->id();
    $t->integer('app_id');
});
Schema::create('app_version_plan_tasks', function (Blueprint $t) {
    $t->id();
    $t->integer('plan_id');
    foreach (['version', 'market_channel', 'submitted_at', 'listed_at', 'status'] as $f) {
        $t->string($f)->nullable();
    }
});
Carbon::setTestNow(Carbon::parse('2026-10-30 12:30:00', 'Asia/Shanghai'));
$base = ['app_id' => 1, 'status' => 'ok', 'risk_score' => 80, 'compliance_mode' => 1, 'ad_switch' => 0, 'user_uuid' => 'device-a', 'device_sn' => 'install-a', 'client_ip' => '192.0.2.1', 'market_channel' => 'huawei', 'app_version' => '1.0', 'route' => 'api/report', 'touch_sample_count' => 1, 'created_at' => '2026-10-30 10:00:00'];
DB::table('risk_probe_logs')->insert(['id' => 1] + $base);
$ledger = app(RiskLedgerService::class);
$ledger->applyLog(RiskProbeLog::find(1));
$out = ['scope_created' => DB::table('risk_scopes')->count(), 'applied_count' => DB::table('risk_ledger_applied')->count(), 'cursor_after_realtime_apply' => $ledger->cursor(), 'caught_up_after_realtime_apply' => $ledger->caughtUp()];
$engine = new class($ledger) extends RiskReleaseEngine
{
    public function evaluateDevice(int $appId, string $device): ?array
    {
        return null;
    }
};
$request = Request::create('/api/risk/activity-events/batch', 'POST', ['events' => [['event_id' => 'e1', 'session_id' => 's1', 'occurred_at' => Carbon::now()->timestamp, 'foreground' => true, 'screen_interactive' => true, 'interaction' => true, 'event_seq' => 1]]], [], [], ['HTTP_APP_ID' => '1', 'HTTP_UUID' => 'device-a', 'HTTP_DEVICE_SN' => 'install-a']);
Request::macro('authUserId', fn () => 123);
$app->instance('request', $request);
$out['activity_result'] = (new ActivityEventService($engine))->ingest($request);
$out['scope_user_id'] = DB::table('risk_scopes')->value('user_id');
$out['intake_user_id'] = DB::table('risk_intakes')->value('user_id');
$out['dossier_account_count'] = DB::table('risk_ip_dossiers')->value('account_count');
$out['timeline_types_after_activity'] = DB::table('risk_timeline')->pluck('event_type')->all();
DB::table('app_version_plans')->insert(['id' => 1, 'app_id' => 1]);
DB::table('app_version_plan_tasks')->insert(['id' => 1, 'plan_id' => 1, 'version' => '1.0', 'market_channel' => 'huawei', 'submitted_at' => '2026-10-30 09:00:00', 'status' => '审核中']);
$realEngine = app(RiskReleaseEngine::class);
$scope = DB::table('risk_scopes')->first();
$out['review_initial'] = $realEngine->blockers($scope);
DB::table('app_version_plan_tasks')->where('id', 1)->update(['status' => '已上架', 'listed_at' => '2026-10-30 11:00:00']);
$out['review_after_listing'] = $realEngine->blockers($scope);
foreach (range(2, 202) as $id) {
    DB::table('risk_probe_logs')->insert(['id' => $id, 'created_at' => '2026-10-30 12:00:00', 'risk_score' => 0, 'compliance_mode' => 0, 'ad_switch' => 1] + $base);
}
$out['review_after_201_later_visits'] = $realEngine->blockers($scope);
DB::table('risk_releases')->insert(['ip' => '2001:db8::1', 'released_at' => '2026-10-30 12:00:00', 'rule_id' => 'continuous-use-release', 'rule_version' => '1', 'idempotency_key' => 'test']);
$method = new ReflectionMethod(RiskReleaseQuery::class, 'apply');
foreach (['2001:db8::1', '2001:0DB8:0000:0000:0000:0000:0000:0001'] as $ip) {
    $q = DB::table('risk_releases');
    $method->invoke(app(RiskReleaseQuery::class), $q, ['ip' => $ip]);
    $out['ipv6_search_counts'][] = $q->count();
}

DB::table('app_version_plan_tasks')->delete();
Request::flushMacros();
DB::table('risk_activity_events')->delete();
$batch = [];
for ($seconds = 1830; $seconds >= 0; $seconds -= 30) {
    $batch[] = ['event_id' => 'bulk-'.$seconds, 'session_id' => 'bulk-session', 'occurred_at' => Carbon::now()->timestamp - $seconds, 'foreground' => true, 'screen_interactive' => true, 'interaction' => true, 'event_seq' => intdiv(1830 - $seconds, 30) + 1];
}
$request = Request::create('/api/risk/activity-events/batch', 'POST', ['events' => $batch], [], [], ['HTTP_APP_ID' => '1', 'HTTP_UUID' => 'device-a', 'HTTP_DEVICE_SN' => 'install-a']);
$app->instance('request', $request);
$out['single_bulk_backfill_without_token'] = (new ActivityEventService($realEngine))->ingest($request);
$out['release_count_after_bulk'] = DB::table('risk_releases')->where('ip', '192.0.2.1')->count();
$out['scope_state_after_bulk'] = DB::table('risk_scopes')->value('state');
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),PHP_EOL;
