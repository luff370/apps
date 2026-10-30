<?php

/** Read-only query alternatives; no production code or schema changes. */
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Dao\Risk\RiskProbeLogDao;
use App\Models\RiskProbeLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$options = getopt('', ['output:']);
$output = $options['output'] ?? sys_get_temp_dir().'/risk-experiments.json';
DB::statement('SET SESSION TRANSACTION READ ONLY');
DB::statement('SET SESSION MAX_EXECUTION_TIME=15000');
$last = DB::table('risk_probe_logs')->orderByDesc('id')->first();
Carbon::setTestNow(Carbon::parse($last->created_at)->endOfDay());
$appId = '10027';
$log = DB::table('risk_probe_logs')->where('app_id', $appId)->where('status', 'ok')->orderByDesc('id')->first();
$dao = app(RiskProbeLogDao::class);
$results = [];
$measure = function (string $name, callable $callback, int $runs = 3) use (&$results) {
    $times = [];
    $value = null;
    for ($i = 0; $i < $runs; $i++) {
        $start = microtime(true);
        $value = $callback();
        $times[] = round((microtime(true) - $start) * 1000, 2);
    }sort($times);
    $results[$name] = ['runs' => $times, 'median_ms' => $times[(int) floor(count($times) / 2)]];
    echo $name.' '.json_encode($times),PHP_EOL;

    return $value;
};
$hint = function ($query, $index) {
    $query->getQuery()->from(DB::raw('`risk_probe_logs` FORCE INDEX (`'.$index.'`)'));

    return $query;
};
// Date predicates and device grouping are identical; only the already-existing index choice changes.
$filter = ['app_id' => $appId, 'time' => 'lately30'];
try {
    $a = $measure('device_month.original_index', fn () => $dao->latestDeviceIds($filter)->toBase()->get()->pluck('id')->sort()->values()->all(), 1);
} catch (Throwable$e) {
    $a = null;
    $results['device_month.original_index'] = ['error' => 'query exceeded 15 second guard'];
}
$b = $measure('device_month.existing_time_index', fn () => $hint($dao->latestDeviceIds($filter), 'rpl_app_time_agg')->toBase()->get()->pluck('id')->sort()->values()->all());
$results['device_month.same_ids'] = $a === null ? null : $a === $b;
// Group once and return page + total using a window, instead of separate full grouping for COUNT and page.
$make = fn () => $hint($dao->latestDeviceIds($filter), 'rpl_app_time_agg');
$old = $measure('device_month.two_groupings', function () use ($make) {
    $count = DB::query()->fromSub($make(), 'd')->count();
    $ids = $make()->orderByDesc('id')->limit(20)->toBase()->pluck('id')->all();

    return ['count' => $count, 'ids' => $ids];
});
$new = $measure('device_month.window_group_once', function () use ($make) {
    $rows = $make()->selectRaw('COUNT(*) OVER () as total')->orderByDesc('id')->limit(20)->toBase()->get();

    return ['count' => (int) ($rows->first()->total ?? 0), 'ids' => $rows->pluck('id')->all()];
});
$results['device_month.same_page_and_count'] = $old === $new;
// Explicit UUID/IP modes are a proposed UI/API feature; do not silently replace the old broad keyword semantics.
$uuid = (string) ($log->user_uuid ?: $log->device_sn);
$measure('keyword.legacy_uuid', fn () => $dao->search(['keyword' => $uuid])->count());
$measure('keyword.explicit_uuid', fn () => RiskProbeLog::query()->where('status', 'ok')->where(fn ($q) => $q->where('user_uuid', $uuid)->orWhere('device_sn', $uuid))->count());
$measure('keyword.legacy_ip', fn () => $dao->search(['keyword' => $log->client_ip])->count());
$measure('keyword.explicit_ip', fn () => RiskProbeLog::query()->where('status', 'ok')->where('client_ip', $log->client_ip)->count());
$rare = ['app_id' => $appId, 'decision' => 'verify'];
$baseline = $measure('event_verify.wide_page', fn () => $dao->search($rare)->orderByDesc('id')->limit(20)->get(RiskProbeLog::adminListColumns())->pluck('id')->all());
$fast = $measure('event_verify.ids_then_rows', function () use ($dao, $rare, $hint) {
    $ids = $hint($dao->search($rare), 'rpl_app_device_snapshot')->orderByDesc('id')->limit(20)->toBase()->pluck('id')->all();

    return RiskProbeLog::query()->whereIn('id', $ids)->orderByDesc('id')->get(RiskProbeLog::adminListColumns())->pluck('id')->all();
});
$results['event_verify.same_page'] = $baseline === $fast;
$offset = 199980;
$deep = $measure('event_deep.offset', fn () => $dao->search(['app_id' => $appId])->orderByDesc('id')->offset($offset)->limit(20)->get(RiskProbeLog::adminListColumns())->pluck('id')->all());
// Navigation would obtain this boundary from the previous page, not by running this setup OFFSET.
$boundary = $dao->search(['app_id' => $appId])->orderByDesc('id')->offset($offset - 1)->limit(1)->value('id');
$cursor = $measure('event_deep.cursor_page_only', fn () => $dao->search(['app_id' => $appId])->where('id', '<', $boundary)->orderByDesc('id')->limit(20)->get(RiskProbeLog::adminListColumns())->pluck('id')->all());
$results['event_deep.same_page'] = $deep === $cursor;
$results['notes'] = ['Local service/query timings, not HTTP timings', 'Median of 3 except guarded original month grouping', 'Explicit search modes have narrower, intentional semantics', 'Cursor result excludes boundary preparation and exact COUNT', 'Window pagination needs a fallback COUNT for an out-of-range empty page'];
file_put_contents($output, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
