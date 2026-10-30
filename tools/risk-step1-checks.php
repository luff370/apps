<?php

/** Read-only verification of optimized response shapes and exact grouped pagination. */
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Dao\Risk\RiskProbeLogDao;
use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\RiskAdminService;
use App\Services\Risk\RiskDossierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$options = getopt('', ['database:', 'output:']);
if (isset($options['database'])) {
    if (! preg_match('/^risk_perf_fixture_[a-f0-9]{12}$/', $options['database'])
        || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('Only local isolated fixtures are allowed');
    }
    $connection = config('database.connections.mysql');
    $connection['database'] = $options['database'];
    config(['database.connections.step1_checks' => $connection, 'database.default' => 'step1_checks']);
}
DB::statement('SET SESSION MAX_EXECUTION_TIME=10000');
DB::statement('SET SESSION TRANSACTION READ ONLY');
$measurements = [];
$measure = function (string $name, callable $call) use (&$measurements) {
    $times = [];
    for ($i = 0; $i < 3; $i++) {
        unset($value);
        $start = microtime(true);
        $value = $call();
        $times[] = round((microtime(true) - $start) * 1000, 2);
    }
    sort($times);
    $measurements[$name] = ['wall_ms' => $times, 'median_ms' => $times[1], 'response_bytes' => strlen(json_encode($value))];

    return $value;
};
$checks = [];
if (isset($options['database'])) {
    $service = app(RiskDossierService::class);
    $id = IpNormalizer::idFor('198.18.0.1');
    $legacy = $measure('dossier.legacy_detail', fn () => $service->detail($id));
    $compact = $measure('dossier.compact_detail', fn () => $service->detail($id, false));
    $list = $measure('dossier.compact_list', fn () => $service->list(['compact' => true, 'include_options' => false], 1, 20));
    $checks['compact_counts_preserved'] = $compact['device_count'] === count($legacy['devices'])
        && $compact['account_count'] === count($legacy['accounts']);
    $checks['compact_no_placeholder_arrays'] = $compact['devices'] === [] && $compact['accounts'] === []
        && ! isset($compact['scopes']) && $list['options'] === null;
    foreach (['devices', 'accounts'] as $kind) {
        $expected = $legacy[$kind];
        usort($expected, fn ($a, $b) => strcmp($a['key'], $b['key']));
        foreach ([1, 2, (int) ceil(count($expected) / 20), (int) ceil(count($expected) / 20) + 1] as $page) {
            $result = $measure('relations.'.$kind.'.page'.$page, fn () => $service->relations($id, $kind, $page, 20));
            $checks[$kind.'.page'.$page] = $result['count'] === count($expected)
                && array_column($result['list'], 'key') === array_column(array_slice($expected, ($page - 1) * 20, 20), 'key');
        }
    }
} else {
    $sample = DB::table('risk_probe_logs')->where('status', 'ok')->orderByDesc('id')->first();
    $service = app(RiskAdminService::class);
    $dao = app(RiskProbeLogDao::class);
    $app->instance('request', Request::create('/', 'GET', ['page' => 1, 'limit' => 20]));
    foreach (['device' => $sample->device_identity, 'ip' => $sample->client_ip, 'install' => $sample->device_sn] as $type => $keyword) {
        $filter = ['keyword_type' => $type, 'keyword' => (string) $keyword];
        $result = $measure('event.explicit.'.$type, fn () => $service->eventList($filter));
        $reference = $dao->search($filter)->orderByDesc('id')->pluck('id')->all();
        $checks['explicit.'.$type] = (int) $result['count'] === count($reference)
            && array_column($result['list'], 'id') === array_slice($reference, 0, 20);
    }
    $filter = ['app_id' => $sample->app_id, 'include_count' => false];
    $first = $measure('event.list_without_count', fn () => $service->eventList($filter));
    $measure('event.separate_count', fn () => $service->eventCount($filter + ['cursor' => $first['count_cursor']]));
    $second = $measure('event.cursor_page', fn () => $service->eventList($filter + ['cursor' => $first['next_cursor']]));
    $reference = $dao->search(['app_id' => $sample->app_id])->orderByDesc('id')->offset(20)->limit(20)->pluck('id')->all();
    $checks['cursor_matches_offset_page'] = array_column($second['list'], 'id') === $reference && $second['count'] === null;
}
$result = ['fixture' => isset($options['database']), 'scope' => 'Read-only local service calls, no HTTP/auth/frontend time',
    'checks' => $checks, 'measurements' => $measurements];
file_put_contents($options['output'] ?? '/tmp/risk-step1-checks.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
if (in_array(false, $checks, true)) {
    exit(1);
}
