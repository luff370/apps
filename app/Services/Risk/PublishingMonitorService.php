<?php

namespace App\Services\Risk;

use App\Models\AppVersionPlan;
use App\Models\SystemApp;
use App\Models\User;
use App\Services\Risk\Ledger\IpNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PublishingMonitorService
{
    public function board(string $start, string $end, ?int $accountId): array
    {
        $startAt = Carbon::parse($start, 'Asia/Shanghai')->startOfDay();
        $endAt = Carbon::parse($end, 'Asia/Shanghai')->addDay()->startOfDay();
        $accounts = DB::table('risk_monitor_accounts')->where('status', 1)->orderBy('id')->get();
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
                if (!$task->submitted_at || !$task->version) {
                    continue;
                }
                $submitted = Carbon::parse($task->submitted_at);
                $listed = $task->listed_at ? Carbon::parse($task->listed_at) : null;
                if ($submitted->greaterThanOrEqualTo($endAt)) {
                    continue;
                }
                if ($listed && $listed->lt($startAt) && !in_array($task->status, ['审核中', '已提交'], true)) {
                    continue;
                }
                $tasks[] = [
                    'id' => (int) $task->id,
                    'market_channel' => (string) $task->market_channel,
                    'version' => (string) $task->version,
                    'status' => (string) $task->status,
                    'submitted_at' => $submitted->format('Y-m-d H:i:s'),
                    'listed_at' => $listed ? $listed->format('Y-m-d H:i:s') : null,
                ];
            }
            if ($tasks === []) {
                continue;
            }
            $appId = (int) $plan->app_id;
            $apps[$appId] = ['id' => $appId, 'name' => $names[$appId] ?? ('应用 ' . $appId)];
            $planPayload[$appId][] = [
                'id' => (int) $plan->id,
                'version' => (string) $plan->version,
                'tasks' => $tasks,
            ];
        }
        $events = $this->events(array_keys($apps), $accountValues, $startAt, $endAt);
        $truncated = count($events) >= 5000;

        return [
            'as_of' => Carbon::now('Asia/Shanghai')->toIso8601String(),
            'timezone' => 'Asia/Shanghai',
            'complete' => !$truncated,
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

    private function events(array $appIds, array $accounts, Carbon $startAt, Carbon $endAt): array
    {
        if ($appIds === []) {
            return [];
        }
        $identityMap = [];
        if ($accounts !== []) {
            $users = User::query()
                ->whereIn('app_id', $appIds)
                ->where(function ($query) use ($accounts) {
                    $query->whereIn('phone', $accounts)->orWhereIn('account', $accounts);
                })
                ->get(['id', 'app_id', 'uuid', 'phone', 'account']);
            foreach ($users as $user) {
                $label = in_array($user->phone, $accounts, true) ? $user->phone : $user->account;
                if ($user->uuid) {
                    $identityMap[$user->app_id . ':' . $user->uuid] = $label;
                }
            }
        }
        $rows = DB::table('risk_probe_logs')
            ->whereIn('app_id', $appIds)
            ->where('status', 'ok')
            ->where('created_at', '>=', $startAt->toDateTimeString())
            ->where('created_at', '<', $endAt->toDateTimeString())
            ->orderByDesc('id')
            ->limit(5000)
            ->get(['id', 'app_id', 'app_version', 'market_channel', 'created_at', 'client_ip', 'user_uuid', 'device_sn', 'risk_score', 'compliance_mode', 'ad_switch']);
        $events = [];
        foreach ($rows as $row) {
            $device = $row->user_uuid ?: $row->device_sn;
            $account = $identityMap[$row->app_id . ':' . $row->user_uuid] ?? '';
            if ($account === '' && in_array((string) $row->user_uuid, $accounts, true)) {
                $account = (string) $row->user_uuid;
            }
            $risky = (int) $row->compliance_mode === 1 || (int) $row->ad_switch === 0 || (int) $row->risk_score >= 30;
            if ($account === '' && !$risky) {
                continue;
            }
            $events[] = [
                'id' => (int) $row->id,
                'app_id' => (string) $row->app_id,
                'version' => (string) $row->app_version,
                'market_channel' => (string) $row->market_channel,
                'created_at' => Carbon::parse($row->created_at)->format('Y-m-d H:i:s'),
                'account' => $account,
                'user_id' => $account,
                'phone' => $account,
                'device_identity' => (string) $device,
                'ip' => IpNormalizer::normalize($row->client_ip),
                'event_type' => $account !== '' ? 'login' : 'guard',
                'operation' => $account !== '' ? '登录' : '收录',
            ];
        }

        return $events;
    }
}
