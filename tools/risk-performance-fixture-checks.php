<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\RiskProbeLog;
use App\Services\Risk\RiskDossierService;
use App\Services\Risk\RiskLedgerService;
use Illuminate\Support\Facades\DB;

$options = getopt('', ['database:', 'output:']);
$name = $options['database'] ?? '';
if (! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Only local isolated fixtures are allowed');
}if (! preg_match('/^risk_perf_fixture_[a-f0-9]{12}$/', $name)) {
    throw new RuntimeException('Invalid fixture');
}
$c = config('database.connections.mysql');
$c['database'] = $name;
config(['database.connections.fixture_checks' => $c, 'database.default' => 'fixture_checks']);
$tagActual = app(RiskDossierService::class)->list(['reason_code' => 'root'], 1, 20)['count'];
$tagExpected = DB::table('risk_ip_dossiers')->whereJsonContains('tags', ['code' => 'root'])->count();
$ipActual = app(RiskDossierService::class)->list(['keyword' => '198.18.0.1'], 1, 20)['count'];
$ipExpected = DB::table('risk_ip_dossiers')->where('ip', '198.18.0.1')->count();
$writes = [];
$capture = false;
$sql = 0;
$n = 0;
DB::listen(function ($q) use (&$capture, &$sql, &$n) {
    if ($capture) {
        $sql += $q->time;
        $n++;
    }
});
foreach (['quiet' => [0, 0, 1], 'risk_hit_hot_ip' => [80, 1, 0]] as $label => [$score,$mode,$ads]) {
    $times = [];
    $queryCounts = [];
    $sqlTimes = [];
    for ($i = 0; $i < 3; $i++) {
        DB::beginTransaction();
        $capture = true;
        $sql = 0;
        $n = 0;
        $start = microtime(true);
        try {
            $log = new RiskProbeLog(['app_id' => 10027, 'status' => 'ok', 'route' => 'api/synthetic', 'risk_score' => $score, 'risk_reasons' => ['root'], 'compliance_mode' => $mode, 'ad_switch' => $ads, 'client_ip' => '198.18.0.1', 'user_uuid' => md5('synthetic-device-1'), 'device_sn' => 'synthetic-install', 'market_channel' => 'huawei', 'app_version' => '1.0.0', 'touch_sample_count' => 1, 'click_sample_count' => 1, 'swipe_sample_count' => 1]);
            $log->id = 400000 + $i;
            $log->created_at = '2026-10-29 23:59:59';
            app(RiskLedgerService::class)->applyLog($log);
            $times[] = round((microtime(true) - $start) * 1000, 2);
            $queryCounts[] = $n;
            $sqlTimes[] = round($sql, 2);
        } finally {
            $capture = false;
            DB::rollBack();
        }
    }
    $writes[$label] = ['wall_ms' => $times, 'sql_ms' => $sqlTimes, 'query_count' => $queryCounts];
}
$r = ['fixture_only' => true, 'native_json_reason_filter' => ['actual' => $tagActual, 'expected' => $tagExpected], 'exact_ip_filter' => ['actual' => $ipActual, 'expected' => $ipExpected], 'synchronous_applyLog' => $writes, 'note' => 'Write-path tests executed only inside rollback transactions in the isolated synthetic database; no real application tables changed'];
file_put_contents(($options['output'] ?? sys_get_temp_dir().'/risk-fixture-checks.json'), json_encode($r, JSON_PRETTY_PRINT));
echo json_encode($r, JSON_PRETTY_PRINT),PHP_EOL;
