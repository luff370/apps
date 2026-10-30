<?php

/** Read-only Laravel service benchmark. Does not call mutating endpoints or sync commands. */
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\RiskAdminService;
use App\Services\Risk\RiskDossierService;
use App\Services\Risk\RiskProbabilityService;
use App\Services\Risk\RiskReleaseQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$options = getopt('', ['output:', 'database:', 'timeout-ms:', 'repeat:', 'only:']);
$output = $options['output'] ?? sys_get_temp_dir().'/risk-performance-audit';
if (isset($options['database'])) {
    // Only permit the isolated, synthetic audit schemas, never arbitrary application databases.
    if (! preg_match('/^risk_perf_fixture_[a-f0-9]{12}$/', $options['database'])) {
        throw new RuntimeException('Invalid fixture database name');
    }
    $connection = config('database.connections.mysql');
    $connection['database'] = $options['database'];
    config(['database.connections.risk_audit' => $connection, 'database.default' => 'risk_audit']);
}
$timeout = max(100, min(30000, (int) ($options['timeout-ms'] ?? 5000)));
DB::statement('SET SESSION MAX_EXECUTION_TIME='.$timeout);
DB::statement('SET SESSION TRANSACTION READ ONLY');
$tables = DB::select("SELECT table_name, table_rows, data_length, index_length FROM information_schema.tables WHERE table_schema=database() AND (table_name LIKE 'risk_%' OR table_name IN ('users','app_version_plans','app_version_plan_tasks'))");
$inventory = [];
foreach ($tables as $table) {
    $name = $table->TABLE_NAME;
    $inventory[$name] = ['estimated_rows' => (int) $table->TABLE_ROWS, 'rows' => DB::table($name)->count(), 'data_bytes' => (int) $table->DATA_LENGTH, 'index_bytes_cached' => (int) $table->INDEX_LENGTH];
}
$last = DB::table('risk_probe_logs')->orderByDesc('id')->first(['id', 'created_at']);
$anchor = Carbon::parse($last->created_at)->endOfDay();
Carbon::setTestNow($anchor);
$hot = DB::table('risk_probe_logs')->selectRaw('app_id, COUNT(*) n')->groupBy('app_id')->orderByDesc('n')->first();
$appId = (string) $hot->app_id;
$sample = DB::table('risk_probe_logs')->where('app_id', $appId)->where('status', 'ok')->orderByDesc('id')->first();
$uuid = (string) ($sample->user_uuid ?: $sample->device_sn);
$version = (string) $sample->app_version;
$channel = (string) $sample->market_channel;
$ip = (string) $sample->client_ip;
$ipPrefix = implode('.', array_slice(explode('.', $ip), 0, 2)).'.';
$cases = [];
$add = function ($id, $module, $call, $request = []) use (&$cases) {
    $cases[] = compact('id', 'module', 'call', 'request');
};
$callAdmin = fn ($method, $filter) => app(RiskAdminService::class)->$method($filter);
$add('overview.global', 'overview', fn () => $callAdmin('overview', []));
$add('overview.app', 'overview', fn () => $callAdmin('overview', ['app_id' => $appId]));
foreach (['today', 'lately7', 'lately30'] as $time) {
    foreach (['global' => [], 'app' => ['app_id' => $appId]] as $scope => $filter) {
        $add('overview.'.$scope.'.'.$time, 'overview', fn () => $callAdmin('overview', $filter + ['time' => $time]));
    }
}
$add('overview.app.date_range', 'overview', fn () => $callAdmin('overview', ['app_id' => $appId, 'time' => [$anchor->copy()->subDays(29)->startOfDay()->toDateTimeString(), $anchor->toDateTimeString()]]));
$add('overview.missing_app', 'overview', fn () => $callAdmin('overview', ['app_id' => '4294967294']));
$filters = [
    'global' => [], 'app' => ['app_id' => $appId], 'today' => ['time' => 'today'],
    'app.today' => ['app_id' => $appId, 'time' => 'today'], 'app.last7' => ['app_id' => $appId, 'time' => 'lately7'],
    'app.last30' => ['app_id' => $appId, 'time' => 'lately30'], 'channel' => ['market_channel' => $channel],
    'app.channel' => ['app_id' => $appId, 'market_channel' => $channel], 'version.contains' => ['version' => $version],
    'app.version' => ['app_id' => $appId, 'version' => $version],
    'keyword.uuid.hit' => ['keyword' => $uuid], 'keyword.uuid.miss' => ['keyword' => 'audit-missing-000000'],
    'keyword.ip.exact' => ['keyword' => $ip], 'keyword.ip.prefix' => ['keyword' => $ipPrefix],
    'keyword.reason' => ['keyword' => 'root'], 'keyword.version' => ['keyword' => $version],
    'app.keyword.uuid' => ['app_id' => $appId, 'keyword' => $uuid], 'app.keyword.miss' => ['app_id' => $appId, 'keyword' => 'audit-missing-000000'],
    'combined' => ['app_id' => $appId, 'market_channel' => $channel, 'version' => $version, 'time' => 'lately30', 'risk_level' => 'high'],
];
foreach (['pass', 'verify', 'limit', 'block'] as $value) {
    $filters['decision.'.$value] = ['decision' => $value];
    $filters['app.decision.'.$value] = ['app_id' => $appId, 'decision' => $value];
}
foreach (['normal', 'watch', 'high', 'critical'] as $value) {
    $filters['level.'.$value] = ['risk_level' => $value];
}
foreach (['device' => 'deviceList', 'event' => 'eventList'] as $module => $method) {
    foreach ($filters as $label => $filter) {
        $add($module.'.'.$label, $module, fn () => $callAdmin($method, $filter));
    }
    foreach ([100, 1000, 10000] as $page) {
        $add($module.'.app.page'.$page, $module, fn () => $callAdmin($method, ['app_id' => $appId]), ['page' => $page, 'limit' => 20]);
    }
    $add($module.'.app.limit100', $module, fn () => $callAdmin($method, ['app_id' => $appId]), ['limit' => 100]);
}
foreach (['guard', 'report'] as $type) {
    $add('event.type.'.$type, 'event', fn () => $callAdmin('eventList', ['event_type' => $type]));
    $add('event.app.type.'.$type, 'event', fn () => $callAdmin('eventList', ['event_type' => $type, 'app_id' => $appId]));
}
foreach (['' => [], 'app' => ['app_id' => $appId]] as $scope => $filter) {
    foreach (['', 'farm', 'multi_account'] as $type) {
        $add('clusters.'.($scope ?: 'global').'.'.($type ?: 'all'), 'clusters', fn () => $callAdmin('clusters', $filter + ['cluster_type' => $type]));
    }
}
$add('clusters.keyword.hit', 'clusters', fn () => $callAdmin('clusters', ['keyword' => $uuid]));
$add('clusters.keyword.miss', 'clusters', fn () => $callAdmin('clusters', ['keyword' => 'audit-missing-000000']));
$add('device.detail', 'device_detail', fn () => app(RiskAdminService::class)->deviceDetail((int) $sample->id));
$add('device.graph.id', 'device_detail', fn () => app(RiskAdminService::class)->deviceGraph((int) $sample->id));
$add('device.graph.identity', 'device_detail', fn () => app(RiskAdminService::class)->deviceGraph($uuid));
$add('device.detail.missing', 'device_detail', fn () => app(RiskAdminService::class)->deviceDetail(PHP_INT_MAX));
foreach ([7, 30, 180] as $days) {
    $start = $anchor->copy()->subDays($days - 1)->toDateString();
    $end = $anchor->toDateString();
    $add('probability.summary.'.$days, 'probability', fn () => app(RiskProbabilityService::class)->summary($start, $end));
    $add('probability.ranking.'.$days, 'probability', fn () => app(RiskProbabilityService::class)->ranking($start, $end, 1, 20));
    foreach (['flow', 'access'] as $kind) {
        $add('probability.'.$kind.'.'.$days, 'probability', fn () => app(RiskProbabilityService::class)->trends($kind, $days, $end));
    }
}
$add('probability.summary.historical', 'probability', fn () => app(RiskProbabilityService::class)->summary($anchor->copy()->subDays(29)->toDateString(), $anchor->copy()->subDay()->toDateString()));
$dossierFilters = ['all' => [], 'app' => ['app_id' => $appId], 'state.high' => ['state' => 'high'], 'state.watch' => ['state' => 'watch'], 'state.released' => ['state' => 'released'], 'channel' => ['market_channel' => $channel], 'reason' => ['reason_code' => 'root'], 'ip.hit' => ['keyword' => $ip], 'ip.prefix' => ['keyword' => $ipPrefix], 'uuid.hit' => ['keyword' => $uuid], 'keyword.miss' => ['keyword' => 'audit-missing-000000'], 'combined' => ['app_id' => $appId, 'market_channel' => $channel, 'state' => 'high', 'reason_code' => 'root']];
foreach ($dossierFilters as $label => $filter) {
    $add('dossiers.'.$label, 'dossiers', fn () => app(RiskDossierService::class)->list($filter, 1, 20));
}
$dossier = DB::table('risk_ip_dossiers')->first();
if ($dossier) {
    $dossierId = IpNormalizer::idFor($dossier->ip);
    $add('dossier.detail', 'dossiers', fn () => app(RiskDossierService::class)->detail($dossierId));
    foreach (['apps', 'devices', 'accounts', 'tags'] as $kind) {
        $add('dossier.relations.'.$kind, 'dossiers', fn () => app(RiskDossierService::class)->relations($dossierId, $kind, 1, 20));
    }foreach (['all' => [], 'app' => ['app_id' => $appId], 'reason' => ['reason_code' => 'root'], 'kind' => ['event_kind' => 'risk_hit']] as $label => $filter) {
        $add('dossier.timeline.'.$label, 'dossiers', fn () => app(RiskDossierService::class)->timeline($dossierId, $filter, 1, 20));
    }
} else {
    $add('dossier.detail.empty', 'dossiers', fn () => app(RiskDossierService::class)->detail(IpNormalizer::idFor('198.18.0.1')));
    $add('dossier.timeline.empty', 'dossiers', fn () => app(RiskDossierService::class)->timeline(IpNormalizer::idFor('198.18.0.1'), [], 1, 20));
}
$releaseFilters = ['all' => [], 'app' => ['app_id' => $appId], 'ip.hit' => ['ip' => $ip], 'ip.prefix' => ['ip' => $ipPrefix], 'device' => ['device_keyword' => $uuid], 'user' => ['user_keyword' => '123'], 'unregistered' => ['unregistered' => true], 'channel' => ['market_channel' => $channel], 'reason' => ['reason_code' => 'root'], 'dates' => ['start_date' => $anchor->copy()->subDays(29)->toDateString(), 'end_date' => $anchor->toDateString()], 'combined' => ['app_id' => $appId, 'market_channel' => $channel, 'reason_code' => 'root']];
foreach ($releaseFilters as $label => $filter) {
    $add('releases.'.$label, 'releases', fn () => app(RiskReleaseQuery::class)->list($filter, 1, 20));
}
$releaseId = (int) (DB::table('risk_releases')->max('id') ?? 1);
$add('release.detail', 'releases', fn () => app(RiskReleaseQuery::class)->detail($releaseId));
foreach ([30, 180, 365] as $days) {
    $add('publishing.'.$days, 'publishing', fn () => app(App\Services\Risk\PublishingMonitorService::class)->board($anchor->copy()->subDays($days - 1)->toDateString(), $anchor->toDateString(), null));
}
$add('catalog.release_rules', 'catalog', fn () => App\Services\Risk\Ledger\ReleaseRule::payload());
$add('catalog.strategy_create', 'catalog', fn () => app(App\Services\Risk\RiskStrategyService::class)->createForm());
$add('catalog.strategy_edit', 'catalog', fn () => app(App\Services\Risk\RiskStrategyService::class)->editForm(1));
$add('catalog.list_create_blacklist', 'catalog', fn () => app(App\Services\Risk\RiskListService::class)->createForm('blacklist'));
$add('catalog.list_create_watchlist', 'catalog', fn () => app(App\Services\Risk\RiskListService::class)->createForm('watchlist'));
$add('monitor_accounts.list', 'monitor_accounts', fn () => app(App\Services\Risk\MonitorAccountService::class)->list());
$add('strategy.all', 'strategy', fn () => app(App\Services\Risk\RiskStrategyService::class)->list(['app_id' => '']));
$add('strategy.keyword', 'strategy', fn () => app(App\Services\Risk\RiskStrategyService::class)->list(['app_id' => '', 'keyword' => 'Device-Env']));
foreach (['all' => ['app_id' => ''], 'blacklist' => ['app_id' => '', 'list_type' => 'blacklist'], 'watchlist' => ['app_id' => '', 'list_type' => 'watchlist'], 'keyword' => ['app_id' => '', 'keyword' => 'audit-missing'], 'app' => ['app_id' => $appId]] as $label => $filter) {
    $add('lists.'.$label, 'lists', fn () => app(App\Services\Risk\RiskListService::class)->getAllByPage($filter, ['*'], ['id' => 'desc']));
}

$queries = [];
$capture = false;
DB::listen(function ($query) use (&$queries, &$capture) {
    if ($capture) {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings, 'ms' => round($query->time, 3)];
    }
});
$status = function () {
    $values = [];
    foreach (DB::select("SHOW SESSION STATUS WHERE Variable_name IN ('Handler_read_key','Handler_read_next','Handler_read_rnd_next','Created_tmp_disk_tables','Created_tmp_tables','Sort_rows')") as $row) {
        $values[$row->Variable_name] = (int) $row->Value;
    }

return $values;
};
$report = ['generated_at' => gmdate(DATE_ATOM), 'fixture' => isset($options['database']), 'mysql' => DB::selectOne('SELECT VERSION() v')->v, 'anchor' => $anchor->toDateTimeString(), 'inventory' => $inventory, 'queue' => config('queue.default'), 'cache' => config('cache.default'), 'cursor' => (int) (DB::table('risk_ledger_meta')->where('id', 1)->value('cursor_id') ?? 0), 'source_max_id' => (int) $last->id, 'statement_timeout_ms' => $timeout, 'scope' => 'Laravel read-only service calls; excludes HTTP, authentication and frontend rendering', 'results' => []];
foreach ($cases as $case) {
    if (isset($options['only']) && ! array_filter(explode(',', $options['only']), fn ($part) => str_contains($case['id'], $part))) {
        continue;
    }
    $app->instance('request', Request::create('/performance-audit', 'GET', $case['request'] + ['page' => 1, 'limit' => 20]));
    for ($run = 1; $run <= max(1, (int) ($options['repeat'] ?? 1)); $run++) {
        $queries = [];
        $before = $status();
        $capture = true;
        $started = microtime(true);
        $error = null;
        $result = null;
        try {
            $result = ($case['call'])();
        } catch (Throwable$e) {
            $error = ['class' => get_class($e), 'code' => (string) $e->getCode()];
            if ($e instanceof Illuminate\Database\QueryException) {
                $queries[] = ['sql' => $e->getSql(), 'bindings' => $e->getBindings(), 'ms' => $timeout, 'failed' => true];
                $error['driver_code'] = $e->errorInfo[1] ?? null;
            }
        }
        $elapsed = round((microtime(true) - $started) * 1000, 2);
        $capture = false;
        $after = $status();
        $delta = [];
        foreach ($after as $key => $value) {
            $delta[$key] = $value - ($before[$key] ?? 0);
        }
        $sqlMs = array_sum(array_column($queries, 'ms'));
        usort($queries, fn ($a, $b) => $b['ms'] <=> $a['ms']);
        $top = [];
        foreach (array_slice($queries, 0, 3) as $q) {
            $plan = [];
            try {
                $plan = DB::select('EXPLAIN '.$q['sql'], $q['bindings']);
            } catch (Throwable$ignored) {
            }$top[] = ['sql' => $q['sql'], 'ms' => $q['ms'], 'failed' => $q['failed'] ?? false, 'plan' => $plan];
        }
        $row = ['id' => $case['id'], 'module' => $case['module'], 'run' => $run, 'wall_ms' => $elapsed, 'sql_ms' => round($sqlMs, 2), 'non_sql_ms' => round(max(0, $elapsed - $sqlMs), 2), 'queries' => count($queries), 'result_count' => is_array($result) ? ($result['count'] ?? null) : null, 'complete' => is_array($result) ? ($result['complete'] ?? null) : null, 'response_bytes' => $result === null ? 0 : strlen(json_encode($result)), 'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2), 'status_delta' => $delta, 'error' => $error, 'top_sql' => $top];
        $report['results'][] = $row;
        echo json_encode(array_intersect_key($row, array_flip(['id', 'run', 'wall_ms', 'sql_ms', 'queries', 'result_count', 'complete', 'error']))),PHP_EOL;
        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0775, true);
        }file_put_contents($output.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        unset($result);
        gc_collect_cycles();
    }
}
$csv = fopen($output.'.csv', 'w');
fputcsv($csv, ['case', 'module', 'run', 'wall_ms', 'sql_ms', 'non_sql_ms', 'queries', 'count', 'complete', 'response_bytes', 'error']);
foreach ($report['results'] as $row) {
    fputcsv($csv, [$row['id'], $row['module'], $row['run'], $row['wall_ms'], $row['sql_ms'], $row['non_sql_ms'], $row['queries'], $row['result_count'], $row['complete'], $row['response_bytes'], $row['error'] ? json_encode($row['error']) : '']);
}fclose($csv);
echo 'Saved '.$output.'.json and .csv',PHP_EOL;
