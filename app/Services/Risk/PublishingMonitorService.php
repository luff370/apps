<?php

namespace App\Services\Risk;

use App\Models\AppVersionPlan;
use App\Models\SystemApp;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PublishingMonitorService
{
    public function board(string $start, string $end, ?int $accountId, string $cursor = '', int $limit = 500, string $channel = ''): array
    {
        return DB::transaction(fn () => $this->buildBoard($start, $end, $accountId, $cursor, $limit, $channel));
    }

    private function buildBoard(string $start, string $end, ?int $accountId, string $cursor = '', int $limit = 500, string $channel = ''): array
    {
        $startAt = Carbon::parse($start, 'Asia/Shanghai')->startOfDay();
        $endAt = Carbon::parse($end, 'Asia/Shanghai')->addDay()->startOfDay();
        $accounts = DB::table('risk_monitor_accounts')->where('status', 1)->orderBy('id')->get();
        $allAccounts = $accounts->all();
        if ($accountId) {
            $accounts = $accounts->where('id', $accountId)->values();
        }
        $accountValues = $accounts->pluck('account')->filter()->values()->all();
        $plans = AppVersionPlan::query()->with('tasks')->orderByDesc('id')->get();
        $apps = [];
        $planPayload = [];
        $names = SystemApp::idToNameMap();
        foreach ($plans as $plan) {
            $tasks = [];
            foreach ($plan->tasks as $task) {
                if (! $task->submitted_at || ! $task->version) {
                    continue;
                }
                $submitted = Carbon::parse($task->submitted_at);
                $listed = $task->listed_at ? Carbon::parse($task->listed_at) : null;
                if ($submitted->greaterThanOrEqualTo($endAt)) {
                    continue;
                }
                $tasks[] = [
                    'id' => (int) $task->id,
                    'market_channel' => (string) $task->market_channel,
                    'version' => (string) $task->version,
                    'status' => (string) $task->status,
                    'submitted_at' => $submitted->format('H:i:s') === '00:00:00' ? $submitted->toDateString() : $submitted->format('Y-m-d H:i:s'),
                    'listed_at' => $listed ? ($listed->format('H:i:s') === '00:00:00' ? $listed->toDateString() : $listed->format('Y-m-d H:i:s')) : null,
                ];
            }
            if ($tasks === []) {
                continue;
            }
            $appId = (int) $plan->app_id;
            $apps[$appId] = ['id' => $appId, 'name' => $names[$appId] ?? ('应用 '.$appId)];
            $planPayload[$appId][] = [
                'id' => (int) $plan->id,
                'version' => (string) $plan->version,
                'tasks' => $tasks,
            ];
        }
        $filter = ['start' => $start, 'end' => $end, 'account_id' => $accountId ?? '', 'channel' => $channel, 'catalog' => hash('sha256', json_encode([$allAccounts, $planPayload]))];
        $position = RiskQueryCursor::decode($cursor, $filter);
        $page = $position['before'] ?? 1;
        $anchorId = $position['anchor'] ?? (int) DB::table('risk_access_facts')->max('id');
        $limit = max(1, min(1000, $limit));
        $query = app(PublishingQuery::class);
        $eventPage = $query->page($start, $end, $accountId, $channel, $page, $limit, null, 'all', $anchorId);
        $events = $eventPage['list'];
        $aggregates = $query->aggregates($start, $end, $accountId, $channel, $anchorId);
        $candidateCount = $query->candidateCount($start, $end, $accountId, $channel, $anchorId);
        $ledgerComplete = app(RiskLedgerService::class)->caughtUp();

        return [
            'as_of' => Carbon::now('Asia/Shanghai')->toIso8601String(),
            'timezone' => 'Asia/Shanghai',
            'complete' => $ledgerComplete && ! $eventPage['has_more'],
            'aggregates_complete' => $ledgerComplete,
            'aggregates' => $aggregates,
            'unmatched_count' => max(0, $candidateCount - (int) $eventPage['count']),
            'anchor_id' => (string) $anchorId,
            'has_more' => $eventPage['has_more'],
            'next_cursor' => $eventPage['has_more'] ? RiskQueryCursor::encode($filter, $anchorId, $page + 1) : null,
            'catalog' => SystemApp::marketChannelsMap(),
            'accounts' => $accounts->map(fn ($row) => [
                'id' => (int) $row->id,
                'account' => $row->account,
                'subject' => $row->subject_name,
            ])->values(),
            'apps' => array_values($apps),
            'plans' => $planPayload,
            'events' => $events,
            'count' => count($apps),
        ];
    }
}
