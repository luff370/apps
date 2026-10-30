<?php

namespace App\Services\Risk;

use App\Models\SystemApp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RiskProbabilityService
{
    public function __construct(private RiskLedgerService $ledger)
    {
    }

    private ?Carbon $snapshotAt = null;

    public function board(string $start, string $end, int $flowDays, int $accessDays, int $page, int $limit): array
    {
        return DB::transaction(function () use ($start, $end, $flowDays, $accessDays, $page, $limit) {
            $this->snapshotAt = Carbon::now('Asia/Shanghai');
            try {
                return ['summary' => $this->summary($start, $end), 'ranking' => $this->ranking($start, $end, $page, $limit),
                    'flow' => $this->trends('flow', $flowDays, $this->snapshotAt->toDateString()),
                    'access' => $this->trends('access', $accessDays, $this->snapshotAt->toDateString())];
            } finally {
                $this->snapshotAt = null;
            }
        });
    }

    public function summary(string $start, string $end): array
    {
        [$startAt, $endAt, $asOf, $endDay] = $this->bounds($start, $end);
        $progress = $this->ledger->progress();
        $rangeCovered = $progress['coverage_started_at'] !== null && $startAt->gte(Carbon::parse($progress['coverage_started_at'], 'Asia/Shanghai'));
        $complete = $this->ledger->caughtUp() && $rangeCovered;
        $base = [
            'range' => ['start_date' => $startAt->toDateString(), 'end_date' => $endDay, 'timezone' => 'Asia/Shanghai'],
            'as_of' => $asOf->toIso8601String(),
            'snapshot_at' => $asOf->toIso8601String(),
            'complete' => $complete,
            'intake_ip_count' => null,
            'net_released_ip_count' => null,
            'raw_release_count' => null,
            'revoked_release_count' => null,
            'progress' => $progress,
            'incomplete_reason' => ! $progress['complete'] ? 'backfill_pending' : (! $rangeCovered ? 'before_coverage_start' : null),
            'active_ip_total' => null,
        ];
        if (! $complete) {
            return $base;
        }
        $base['intake_ip_count'] = (int) DB::table('risk_intakes')
            ->where('collected_at', '>=', $startAt->toDateTimeString())
            ->where('collected_at', '<', $endAt->toDateTimeString())
            ->distinct('ip')
            ->count('ip');
        $base['net_released_ip_count'] = (int) DB::table('risk_ip_exits')
            ->where('status', 'effective')
            ->where('exited_at', '>=', $startAt->toDateTimeString())
            ->where('exited_at', '<', $endAt->toDateTimeString())
            ->distinct('ip')
            ->count('ip');
        $base['raw_release_count'] = (int) DB::table('risk_releases')
            ->where('released_at', '>=', $startAt->toDateTimeString())
            ->where('released_at', '<', $endAt->toDateTimeString())
            ->count();
        $base['revoked_release_count'] = (int) DB::table('risk_revocations')
            ->whereIn('result', ['restored', 'partial'])
            ->where('revoked_at', '>=', $startAt->toDateTimeString())
            ->where('revoked_at', '<', $endAt->toDateTimeString())
            ->count();
        $base['active_ip_total'] = $this->activeAt($endDay, $asOf);

        return $base;
    }

    public function trends(string $kind, int $days, ?string $endDate): array
    {
        $days = in_array($days, [7, 30, 180], true) ? $days : 7;
        $end = $endDate ? Carbon::parse($endDate, 'Asia/Shanghai') : Carbon::now('Asia/Shanghai');
        $endDay = $end->toDateString();
        $start = $end->copy()->startOfDay()->subDays($days - 1);
        $complete = $this->ledger->caughtUp();
        $coverage = $this->ledger->progress()['coverage_started_at'];
        $coverageAt = $coverage ? Carbon::parse($coverage, 'Asia/Shanghai') : null;
        $rangeEnd = $end->copy()->startOfDay()->addDay()->min($this->snapshotAt ?? Carbon::now('Asia/Shanghai'));
        $flags = DB::table('risk_day_flags')->whereBetween('day', [$start->toDateString(), $endDay])->pluck('unclassified_logs', 'day');
        $rows = [];
        $grouped = collect();
        $intake = collect();
        $released = collect();
        if ($complete && $kind === 'access') {
            $grouped = DB::table('risk_access_facts')->whereIn('event_type', ['login', 'access'])
                ->where('occurred_at', '>=', $start->toDateTimeString())
                ->where('occurred_at', '<', $rangeEnd->toDateTimeString())
                ->selectRaw('DATE(occurred_at) as day')
                ->selectRaw("SUM(decision = 'pass') as normal_visits")
                ->selectRaw("SUM(decision IN ('verify','limit','block')) as risk_visits")
                ->selectRaw("SUM(decision NOT IN ('pass','verify','limit','block')) as unknown_visits")
                ->groupBy(DB::raw('DATE(occurred_at)'))
                ->get()
                ->keyBy('day');
        } elseif ($complete) {
            $intake = DB::table('risk_intakes')
                ->where('collected_at', '>=', $start->toDateTimeString())
                ->where('collected_at', '<', $rangeEnd->toDateTimeString())
                ->selectRaw('DATE(collected_at) as day, COUNT(DISTINCT ip) as intake')
                ->groupBy(DB::raw('DATE(collected_at)'))
                ->pluck('intake', 'day');
            $released = DB::table('risk_ip_exits')
                ->where('status', 'effective')
                ->where('exited_at', '>=', $start->toDateTimeString())
                ->where('exited_at', '<', $rangeEnd->toDateTimeString())
                ->selectRaw('DATE(exited_at) as day, COUNT(DISTINCT ip) as released')
                ->groupBy(DB::raw('DATE(exited_at)'))
                ->pluck('released', 'day');
        }
        for ($cursor = $start->copy(); $cursor->toDateString() <= $endDay; $cursor->addDay()) {
            $day = $cursor->toDateString();
            $dayComplete = $complete && $coverageAt !== null && $cursor->gte($coverageAt) && ($kind !== 'access' || (int) ($flags[$day] ?? 0) === 0);
            if ($kind === 'access') {
                $row = $grouped[$day] ?? null;
                $rows[] = [
                    'day' => $day,
                    'risk' => $dayComplete ? (int) ($row->risk_visits ?? 0) : null,
                    'normal' => $dayComplete ? (int) ($row->normal_visits ?? 0) : null,
                    'unknown_visits' => $dayComplete ? (int) ($row->unknown_visits ?? 0) : null,
                    'complete' => $dayComplete,
                ];
            } else {
                $rows[] = [
                    'day' => $day,
                    'intake' => $dayComplete ? (int) ($intake[$day] ?? 0) : null,
                    'release' => $dayComplete ? (int) ($released[$day] ?? 0) : null,
                    'complete' => $dayComplete,
                ];
            }
        }

        return [
            'kind' => $kind === 'access' ? 'access' : 'flow',
            'days' => $days,
            'timezone' => 'Asia/Shanghai',
            'as_of' => ($this->snapshotAt ?? Carbon::now('Asia/Shanghai'))->toIso8601String(),
            'complete' => $complete && $coverageAt !== null && $start->gte($coverageAt) && ($kind !== 'access' || $flags->sum() === 0),
            'list' => $rows,
        ];
    }

    public function ranking(string $start, string $end, int $page, int $limit): array
    {
        [$startAt, $endAt, $asOf] = $this->bounds($start, $end);
        $coverage = $this->ledger->progress()['coverage_started_at'];
        $complete = $this->ledger->caughtUp() && $coverage && $startAt->gte(Carbon::parse($coverage, 'Asia/Shanghai'));
        if (! $complete) {
            return ['list' => [], 'count' => 0, 'complete' => false, 'as_of' => $asOf->toIso8601String()];
        }
        $grouped = DB::table('risk_intakes')
            ->where('collected_at', '>=', $startAt->toDateTimeString())
            ->where('collected_at', '<', $endAt->toDateTimeString())
            ->selectRaw('app_id, COUNT(DISTINCT ip) as intake_ip_count, COUNT(DISTINCT device_identity) as device_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN user_id IS NOT NULL AND user_id <> '' THEN user_id END) as account_count")
            ->selectRaw('MAX(collected_at) as last_intake_at')
            ->groupBy('app_id')
            ->orderByDesc('intake_ip_count')
            ->orderBy('app_id');
        $count = (int) DB::query()->fromSub((clone $grouped)->reorder(), 'ranking')->count();
        $slice = $grouped->forPage(max(1, $page), max(1, min(100, $limit)))->get();
        $names = SystemApp::idToNameMap();
        $legacyAccounts = DB::table('risk_intakes')->where('collected_at', '>=', $startAt->toDateTimeString())
            ->where('collected_at', '<', $endAt->toDateTimeString())->whereNotNull('user_id')->where('user_id', '<>', '')->select('app_id', 'user_id');
        $linkedAccounts = DB::table('risk_scope_accounts as a')->join('risk_intakes as i', 'i.scope_id', '=', 'a.scope_id')
            ->where('i.collected_at', '>=', $startAt->toDateTimeString())->where('i.collected_at', '<', $endAt->toDateTimeString())
            ->where('a.first_at', '<', $endAt->toDateTimeString())->select('a.app_id', 'a.user_id');
        $accounts = DB::query()->fromSub($legacyAccounts->union($linkedAccounts), 'accounts')
            ->selectRaw('app_id, COUNT(DISTINCT user_id) as accounts')->groupBy('app_id')->pluck('accounts', 'app_id');
        $list = [];
        foreach ($slice as $row) {
            $list[] = [
                'app_id' => (string) $row->app_id,
                'app_name' => $names[(int) $row->app_id] ?? ('应用 '.$row->app_id),
                'intake_ip_count' => (int) $row->intake_ip_count,
                'device_count' => (int) $row->device_count,
                'account_count' => (int) ($accounts[$row->app_id] ?? 0),
                'last_intake_at' => Carbon::parse($row->last_intake_at)->timezone('Asia/Shanghai')->toIso8601String(),
            ];
        }

        return [
            'list' => $list,
            'count' => $count,
            'complete' => true,
            'as_of' => $asOf->toIso8601String(),
        ];
    }

    private function activeAt(string $endDay, Carbon $asOf): int
    {
        if ($endDay === $asOf->toDateString()) {
            return (int) DB::table('risk_ip_dossiers')->whereIn('state', ['high', 'watch'])->count();
        }
        $at = Carbon::parse($endDay, 'Asia/Shanghai')->addDay()->startOfDay()->toDateTimeString();
        $row = DB::selectOne(
            'SELECT COUNT(DISTINCT e.ip) AS aggregate FROM risk_scope_events e
            INNER JOIN (
                SELECT scope_id, MAX(id) AS id FROM risk_scope_events WHERE occurred_at < ? GROUP BY scope_id
            ) last_event ON last_event.id = e.id
            WHERE e.active_after = 1',
            [$at]
        );

        return (int) ($row->aggregate ?? 0);
    }

    private function bounds(string $start, string $end): array
    {
        $startAt = Carbon::parse($start, 'Asia/Shanghai')->startOfDay();
        $endDay = Carbon::parse($end, 'Asia/Shanghai')->toDateString();
        $asOf = $this->snapshotAt ? $this->snapshotAt->copy() : Carbon::now('Asia/Shanghai');
        $endAt = Carbon::parse($endDay, 'Asia/Shanghai')->addDay()->startOfDay();
        if ($endDay === $asOf->toDateString() && $endAt->greaterThan($asOf)) {
            $endAt = $asOf->copy();
        }

        return [$startAt, $endAt, $asOf, $endDay];
    }
}
