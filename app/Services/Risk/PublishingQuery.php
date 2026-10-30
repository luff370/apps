<?php

namespace App\Services\Risk;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** One canonical matching query shared by full-range counts and detail pagination. */
class PublishingQuery
{
    private function version(string $column): string
    {
        return "CASE WHEN LOWER(SUBSTR($column,1,1)) = 'v' THEN SUBSTR($column,2) ELSE $column END";
    }

    public function matched(string $start, string $end, ?int $monitorId = null, string $channel = '', ?int $anchorId = null)
    {
        $from = Carbon::parse($start, 'Asia/Shanghai')->startOfDay();
        $to = Carbon::parse($end, 'Asia/Shanghai')->addDay()->startOfDay()->min(Carbon::now('Asia/Shanghai'));
        $facts = DB::table('risk_access_facts as f')->where('f.occurred_at', '>=', $from->toDateTimeString())->where('f.occurred_at', '<=', $to->toDateTimeString());
        if ($anchorId !== null) {
            $facts->where('f.id', '<=', $anchorId);
        }
        if ($channel !== '') {
            $facts->where('f.market_channel', $channel);
        }
        $monitors = DB::table('risk_monitor_accounts as m')->select('m.id')->where('m.status', 1)
            ->where(fn ($q) => $q->where('m.app_id', 0)->orWhereColumn('m.app_id', 'f.app_id'))
            ->where(fn ($q) => $q->whereColumn('m.account', 'f.account')->orWhereColumn('m.account', 'f.phone'))
            ->orderByDesc('m.app_id')->orderBy('m.id')->limit(1);
        $candidate = DB::table('app_version_plan_tasks as t')->join('app_version_plans as p', 'p.id', '=', 't.plan_id')
            ->select('t.id')->whereColumn('p.app_id', 'f.app_id')->whereColumn('t.market_channel', 'f.market_channel')
            ->whereRaw($this->version('t.version').' = '.$this->version('f.version_name'))
            ->whereColumn('t.submitted_at', '<=', 'f.occurred_at')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('app_version_plan_tasks as other')->join('app_version_plans as op', 'op.id', '=', 'other.plan_id')
                    ->whereColumn('op.app_id', 'p.app_id')->whereColumn('other.market_channel', 't.market_channel')
                    ->whereRaw($this->version('other.version').' = '.$this->version('t.version'))
                    ->whereColumn('other.submitted_at', 't.submitted_at')->whereColumn('other.id', '<>', 't.id');
            })->whereNotExists(function ($q) {
                $q->selectRaw('1')->from('app_version_plan_tasks as newer')->join('app_version_plans as np', 'np.id', '=', 'newer.plan_id')
                    ->whereColumn('np.app_id', 'p.app_id')->whereColumn('newer.market_channel', 't.market_channel')
                    ->whereRaw($this->version('newer.version').' = '.$this->version('t.version'))
                    ->whereColumn('newer.submitted_at', '>', 't.submitted_at')->whereColumn('newer.submitted_at', '<=', 'f.occurred_at');
            })->orderByDesc('t.submitted_at')->limit(1);
        $facts->select('f.*')->selectSub($monitors, 'monitor_id')->selectSub($candidate, 'matched_task_id');
        $q = DB::query()->fromSub($facts, 'facts')->join('app_version_plan_tasks as task', 'task.id', '=', 'facts.matched_task_id');
        $test = "facts.monitor_id IS NOT NULL AND facts.event_type IN ('login','access')";
        $listedEnd = DB::connection()->getDriverName() === 'sqlite'
            ? "CASE WHEN TIME(task.listed_at) = '00:00:00' THEN DATETIME(task.listed_at, '+1 day') ELSE task.listed_at END"
            : "CASE WHEN TIME(task.listed_at) = '00:00:00' THEN DATE_ADD(task.listed_at, INTERVAL 1 DAY) ELSE task.listed_at END";
        $other = "facts.monitor_id IS NULL AND (facts.event_type = 'guard' OR facts.decision IN ('verify','limit','block')) AND ((task.listed_at IS NOT NULL AND facts.occurred_at < $listedEnd) OR (task.listed_at IS NULL AND task.status IN ('审核中','已提交')))";
        $q->where(fn ($inner) => $inner->whereRaw('('.$test.')')->when($monitorId, fn ($x) => $x->where('facts.monitor_id', $monitorId))->orWhereRaw('('.$other.')'));

        return $q->select('facts.*', 'task.plan_id', 'task.submitted_at', 'task.listed_at', 'task.status as task_status')
            ->selectRaw("CASE WHEN $test THEN 'test' ELSE 'other' END as category");
    }

    public function candidateCount(string $start, string $end, ?int $monitorId, string $channel, int $anchorId): int
    {
        $q = DB::table('risk_access_facts as f')->where('f.id', '<=', $anchorId)
            ->where('f.occurred_at', '>=', Carbon::parse($start, 'Asia/Shanghai')->startOfDay()->toDateTimeString())
            ->where('f.occurred_at', '<=', Carbon::parse($end, 'Asia/Shanghai')->addDay()->startOfDay()->min(Carbon::now('Asia/Shanghai'))->toDateTimeString());
        if ($channel !== '') {
            $q->where('f.market_channel', $channel);
        }
        $known = DB::table('risk_monitor_accounts as m')->selectRaw('1')->where('m.status', 1)
            ->where(fn ($x) => $x->where('m.app_id', 0)->orWhereColumn('m.app_id', 'f.app_id'))
            ->where(fn ($x) => $x->whereColumn('m.account', 'f.account')->orWhereColumn('m.account', 'f.phone'));
        $selected = clone $known;
        if ($monitorId) {
            $selected->where('m.id', $monitorId);
        }

        return $q->where(function ($eligible) use ($known, $selected) {
            $eligible->where(fn ($test) => $test->whereIn('f.event_type', ['login', 'access'])->whereExists($selected))
                ->orWhere(fn ($other) => $other->whereNotExists($known)->where(fn ($risk) => $risk->where('f.event_type', 'guard')->orWhereIn('f.decision', ['verify', 'limit', 'block'])));
        })->count();
    }

    public function aggregates(string $start, string $end, ?int $monitorId = null, string $channel = '', ?int $anchorId = null): array
    {
        $base = DB::query()->fromSub($this->matched($start, $end, $monitorId, $channel, $anchorId), 'matched');
        $concat = DB::connection()->getDriverName() === 'sqlite' ? "app_id || ':' || user_id" : "CONCAT(app_id, ':', user_id)";
        $identity = "CASE WHEN category = 'test' THEN CAST(monitor_id AS CHAR) WHEN user_id IS NOT NULL AND user_id <> '' THEN $concat WHEN device_identity <> '' THEN device_identity ELSE NULL END";

        return $base->select('app_id', 'plan_id', 'version_name')->selectRaw("COUNT(*) as event_count, COUNT(DISTINCT CASE WHEN category = 'test' THEN $identity END) as test_count, COUNT(DISTINCT CASE WHEN category = 'other' THEN $identity END) as other_count, SUM(CASE WHEN user_id IS NULL AND device_identity = '' THEN 1 ELSE 0 END) as unknown_count, MAX(occurred_at) as last_at")
            ->groupBy('app_id', 'plan_id', 'version_name')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function page(string $start, string $end, ?int $monitorId, string $channel, int $page, int $limit, ?string $release = null, string $category = 'all', ?int $anchorId = null): array
    {
        $page = max(1, $page);
        $limit = max(1, min(1000, $limit));
        $category = in_array($category, ['all', 'test', 'other'], true) ? $category : 'all';
        $query = DB::query()->fromSub($this->matched($start, $end, $monitorId, $channel, $anchorId), 'matched');
        if ($release !== null) {
            $parts = explode(':', $release, 3);
            if (count($parts) !== 3) {
                return ['list' => [], 'count' => 0];
            }
            $query->where('app_id', $parts[0])->where('plan_id', $parts[1])->whereRaw($this->version('version_name').' = ?', [$parts[2]]);
        }
        $counts = (clone $query)->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category');
        if (in_array($category, ['test', 'other'], true)) {
            $query->where('category', $category);
        }
        $count = $category === 'all' ? (int) $counts->sum() : (int) ($counts[$category] ?? 0);
        $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->forPage(max(1, $page), max(1, min(1000, $limit)))->get();

        return ['list' => $rows->map(fn ($r) => $this->present($r))->all(), 'count' => $count,
            'category_counts' => ['all' => (int) $counts->sum(), 'test' => (int) ($counts['test'] ?? 0), 'other' => (int) ($counts['other'] ?? 0)],
            'has_more' => $page * $limit < $count];
    }

    public function present(object $row): array
    {
        $at = Carbon::parse($row->occurred_at, 'Asia/Shanghai');
        $submit = Carbon::parse($row->submitted_at, 'Asia/Shanghai');
        $listed = $row->listed_at ? Carbon::parse($row->listed_at, 'Asia/Shanghai') : null;
        $pending = ($submit->format('H:i:s') === '00:00:00' && $submit->isSameDay($at)) || ($listed && $listed->format('H:i:s') === '00:00:00' && $listed->isSameDay($at));
        $stage = $pending ? 'pending' : ($listed ? ($at->gte($listed) ? 'post' : 'review') : (in_array($row->task_status, ['审核中', '已提交'], true) ? 'review' : 'pending'));

        return ['id' => (string) $row->id, 'key' => 'fact:'.$row->id, 'app_id' => (string) $row->app_id,
            'version' => $row->version_name, 'market_channel' => $row->market_channel, 'channel' => \App\Models\SystemApp::marketChannelsMap()[$row->market_channel] ?? $row->market_channel,
            'account' => $row->account ?: '', 'phone' => $row->phone ?: '', 'user_id' => $row->user_id ?: '',
            'device_identity' => $row->device_identity, 'device' => $row->device_identity, 'ip' => $row->ip,
            'event_type' => $row->event_type, 'category' => $row->category, 'stage' => $stage,
            'created_at' => $at->toIso8601String(), 'ms' => $at->timestamp * 1000,
            'type' => $row->event_type === 'login' ? '登录' : ($row->event_type === 'guard' ? '收录' : '访问'),
            'taskKey' => $row->plan_id.':'.$row->matched_task_id];
    }
}
