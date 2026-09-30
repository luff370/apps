<?php

namespace App\Services\Risk;

use App\Dao\Risk\RiskProbeLogDao;
use App\Models\RiskProbeLog;
use App\Models\User;
use App\Services\Service;
use App\Support\Services\DeviceEnvRiskView;
use Illuminate\Support\Facades\DB;

class RiskAdminService extends Service
{
    public function __construct(RiskProbeLogDao $dao)
    {
        $this->dao = $dao;
    }

    public function overview(array $filter): array
    {
        $todayStart = today()->startOfDay()->toDateTimeString();
        $todayEnd = today()->endOfDay()->toDateTimeString();
        $stats = $this->deviceSnapshotStats($filter);

        $blockedToday = (clone $this->dao->search($filter))
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->where('compliance_mode', 1)
            ->count();
        $eventToday = (clone $this->dao->search($filter))
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $multiAccount = (int) DB::query()->fromSub(
            User::query()
                ->select('uuid')
                ->where('uuid', '!=', '')
                ->when(!empty($filter['app_id']), fn ($q) => $q->where('app_id', $filter['app_id']))
                ->groupBy('uuid')
                ->havingRaw('COUNT(*) >= 2'),
            'multi_accounts'
        )->count();

        $farmSuspect = (int) DB::query()->fromSub($this->farmClusterQuery($filter), 'farms')->count();

        $apps = DeviceEnvRiskView::appNameMap();
        $channels = DeviceEnvRiskView::channelMap();
        $topLogs = $this->topRiskDevices($filter);
        $accountCounts = $this->accountCountMap($topLogs->map(fn ($log) => DeviceEnvRiskView::identityFromLog($log))->all());
        $topRiskDevices = [];
        foreach ($topLogs as $log) {
            $row = DeviceEnvRiskView::formatLog($log, $apps, $channels);
            $row['account_count'] = $accountCounts[$row['device_identity']][$row['app_id']] ?? 0;
            $topRiskDevices[] = $row;
        }

        return [
            'summary' => [
                'device_total' => (int) $stats->device_total,
                'high_risk' => (int) $stats->high_risk,
                'blocked_today' => $blockedToday,
                'farm_suspect' => $farmSuspect,
                'multi_account' => $multiAccount,
                'event_today' => $eventToday,
            ],
            'score_distribution' => [
                ['level' => 'normal', 'label' => '正常 0-30', 'count' => (int) $stats->score_normal],
                ['level' => 'watch', 'label' => '观察 30-70', 'count' => (int) $stats->score_watch],
                ['level' => 'high', 'label' => '高风险 70-90', 'count' => (int) $stats->score_high],
                ['level' => 'critical', 'label' => '严重 90-100', 'count' => (int) $stats->score_critical],
            ],
            'decision_distribution' => [
                ['decision' => 'pass', 'count' => (int) $stats->decision_pass],
                ['decision' => 'verify', 'count' => (int) $stats->decision_verify],
                ['decision' => 'limit', 'count' => (int) $stats->decision_limit],
                ['decision' => 'block', 'count' => (int) $stats->decision_block],
            ],
            'top_risk_devices' => $topRiskDevices,
            'trend' => $this->trend($filter),
        ];
    }

    /**
     * 每台设备只取最新一条，分数分布和决策分布一次算完。
     */
    private function deviceSnapshotStats(array $filter): object
    {
        $table = (new RiskProbeLog())->getTable();
        $stats = RiskProbeLog::query()
            ->joinSub($this->dao->latestDeviceIds($filter), 'latest_device', 'latest_device.id', '=', $table . '.id')
            ->selectRaw('COUNT(*) as device_total')
            ->selectRaw('SUM(' . $table . '.risk_score >= 70) as high_risk')
            ->selectRaw('SUM(' . $table . '.risk_score < 30) as score_normal')
            ->selectRaw('SUM(' . $table . '.risk_score >= 30 AND ' . $table . '.risk_score < 70) as score_watch')
            ->selectRaw('SUM(' . $table . '.risk_score >= 70 AND ' . $table . '.risk_score < 90) as score_high')
            ->selectRaw('SUM(' . $table . '.risk_score >= 90) as score_critical')
            ->selectRaw('SUM(' . $table . '.compliance_mode = 0 AND ' . $table . '.ad_switch = 1 AND ' . $table . '.risk_score < 30) as decision_pass')
            ->selectRaw('SUM(' . $table . '.compliance_mode = 0 AND ' . $table . '.ad_switch = 1 AND ' . $table . '.risk_score >= 30) as decision_verify')
            ->selectRaw('SUM(' . $table . '.compliance_mode = 0 AND ' . $table . '.ad_switch = 0) as decision_limit')
            ->selectRaw('SUM(' . $table . '.compliance_mode = 1) as decision_block')
            ->first();

        return $stats ?: (object) [
            'device_total' => 0,
            'high_risk' => 0,
            'score_normal' => 0,
            'score_watch' => 0,
            'score_high' => 0,
            'score_critical' => 0,
            'decision_pass' => 0,
            'decision_verify' => 0,
            'decision_limit' => 0,
            'decision_block' => 0,
        ];
    }

    private function topRiskDevices(array $filter)
    {
        $table = (new RiskProbeLog())->getTable();

        return RiskProbeLog::query()
            ->joinSub($this->dao->latestDeviceIds($filter), 'latest_device', 'latest_device.id', '=', $table . '.id')
            ->orderByDesc($table . '.risk_score')
            ->orderByDesc($table . '.id')
            ->limit(8)
            ->get(array_map(fn ($column) => $table . '.' . $column, RiskProbeLog::adminListColumns()));
    }

    public function deviceList(array $filter): array
    {
        [$page, $limit] = $this->getPageValue();
        $query = $this->dao->latestDeviceQuery($filter);
        $count = (clone $query)->count();
        $list = [];
        if ($count > 0) {
            $rows = (clone $query)->orderByDesc('id')->forPage($page, $limit)->get(RiskProbeLog::adminListColumns());
            $apps = DeviceEnvRiskView::appNameMap();
            $channels = DeviceEnvRiskView::channelMap();
            $identities = [];
            foreach ($rows as $log) {
                $identity = DeviceEnvRiskView::identityFromLog($log);
                if ($identity !== '') {
                    $identities[] = $identity;
                }
            }
            $accountCounts = $this->accountCountMap($identities);
            $firstSeen = $this->firstSeenMap($identities);
            foreach ($rows as $log) {
                $item = DeviceEnvRiskView::formatLog($log, $apps, $channels);
                $item['account_count'] = $accountCounts[$item['device_identity']][$item['app_id']] ?? 0;
                $item['created_at'] = DeviceEnvRiskView::formatTime($firstSeen[$item['device_identity']] ?? $log->created_at);
                $list[] = $item;
            }
        }

        return compact('list', 'count');
    }

    public function deviceDetail(int $id): array
    {
        $log = RiskProbeLog::query()->find($id);
        if (!$log) {
            return [];
        }
        $apps = DeviceEnvRiskView::appNameMap();
        $channels = DeviceEnvRiskView::channelMap();
        $detail = DeviceEnvRiskView::formatLog($log, $apps, $channels);
        $identity = $detail['device_identity'];
        $firstAt = $identity === '' ? null : RiskProbeLog::query()
            ->where('status', 'ok')
            ->where('device_identity', $identity)
            ->min('created_at');
        $detail['created_at'] = DeviceEnvRiskView::formatTime($firstAt);
        $detail['accounts'] = $this->accounts($identity, (int) $detail['app_id']);
        $detail['account_count'] = count($detail['accounts']);
        $probe = is_array($log->probe_json) ? $log->probe_json : [];
        unset($probe['token'], $probe['tk']);
        $detail['feature_package'] = $probe;
        $detail['hardware_scalars'] = [
            'probe_v' => $log->probe_v,
            'env_schema_v' => $log->env_schema_v,
            'platform' => $log->platform,
            'touch_sample_count' => $log->touch_sample_count,
            'click_sample_count' => $log->click_sample_count,
            'swipe_sample_count' => $log->swipe_sample_count,
        ];
        $events = $identity === '' ? collect() : RiskProbeLog::query()
            ->where('status', 'ok')
            ->where('device_identity', $identity)
            ->orderByDesc('id')
            ->limit(20)
            ->get(RiskProbeLog::adminListColumns());
        $detail['recent_events'] = [];
        foreach ($events as $event) {
            $row = DeviceEnvRiskView::formatLog($event, $apps, $channels);
            $detail['recent_events'][] = [
                'id' => $row['id'],
                'event_type' => $row['event_type'],
                'decision' => $row['decision'],
                'risk_score' => $row['risk_score'],
                'block_reason' => $row['block_reason'],
                'created_at' => $row['created_at'],
            ];
        }

        return $detail;
    }

    public function deviceGraph($key): array
    {
        $log = $this->findDeviceLog($key);
        if (!$log) {
            return [
                'device' => null,
                'accounts' => [],
                'similar_devices' => [],
                'summary' => [
                    'account_count' => 0,
                    'similar_device_count' => 0,
                    'risk_score' => 0,
                    'cluster_hint' => '暂无数据',
                    'farm_suspect' => false,
                ],
                'nodes' => [],
                'links' => [],
            ];
        }

        $apps = DeviceEnvRiskView::appNameMap();
        $channels = DeviceEnvRiskView::channelMap();
        $device = DeviceEnvRiskView::formatLog($log, $apps, $channels);
        $accounts = $this->accounts($device['device_identity'], (int) $device['app_id']);
        $device['account_count'] = count($accounts);
        $similar = $this->similarDevices($log, $apps, $channels);

        $nodes = [[
            'id' => 'device_' . $device['id'],
            'name' => $device['device_identity'],
            'category' => 0,
            'symbolSize' => 56,
            'value' => $device['risk_score'],
            'raw' => array_merge(['type' => 'device'], $device),
        ]];
        $links = [];
        foreach ($accounts as $acc) {
            $nodes[] = [
                'id' => 'account_' . $acc['user_id'],
                'name' => $acc['account'],
                'category' => 1,
                'symbolSize' => 28,
                'value' => $acc['user_id'],
                'raw' => array_merge(['type' => 'account'], $acc),
            ];
            $links[] = [
                'source' => 'device_' . $device['id'],
                'target' => 'account_' . $acc['user_id'],
                'relation' => '同设备登录',
            ];
        }
        foreach ($similar as $sim) {
            $nodes[] = [
                'id' => 'sim_' . $sim['id'],
                'name' => $sim['device_identity'],
                'category' => 2,
                'symbolSize' => 36,
                'value' => $sim['similarity'] ?? $sim['risk_score'],
                'raw' => array_merge(['type' => 'similar_device'], $sim),
            ];
            $links[] = [
                'source' => 'device_' . $device['id'],
                'target' => 'sim_' . $sim['id'],
                'relation' => $sim['relation'] ?? '相似设备',
            ];
        }

        return [
            'device' => $device,
            'accounts' => $accounts,
            'similar_devices' => $similar,
            'summary' => [
                'account_count' => count($accounts),
                'similar_device_count' => count($similar),
                'risk_score' => $device['risk_score'],
                'cluster_hint' => count($accounts) >= 2 ? '同设备多账号' : (count($similar) ? '同 IP 关联' : '普通关联'),
                'farm_suspect' => count($similar) >= 3,
            ],
            'nodes' => $nodes,
            'links' => $links,
        ];
    }

    public function eventList(array $filter): array
    {
        [$page, $limit] = $this->getPageValue();
        $query = $this->dao->search($filter);
        $count = (clone $query)->count();
        $list = [];
        if ($count > 0) {
            $apps = DeviceEnvRiskView::appNameMap();
            $channels = DeviceEnvRiskView::channelMap();
            $rows = (clone $query)->orderByDesc('id')->forPage($page, $limit)->get(RiskProbeLog::adminListColumns());
            $items = [];
            foreach ($rows as $log) {
                $items[] = DeviceEnvRiskView::formatLog($log, $apps, $channels);
            }
            $userIds = User::query()
                ->select('uuid', DB::raw('MAX(id) as user_id'))
                ->whereIn('uuid', array_values(array_filter(array_column($items, 'device_identity'))))
                ->groupBy('uuid')
                ->pluck('user_id', 'uuid');
            foreach ($items as &$item) {
                $item['user_id'] = (int) ($userIds[$item['device_identity']] ?? 0);
            }
            unset($item);
            $list = $items;
        }

        return compact('list', 'count');
    }

    public function clusters(array $filter): array
    {
        [$page, $limit] = $this->getPageValue();
        $type = $filter['cluster_type'] ?? '';
        $keyword = strtolower((string) ($filter['keyword'] ?? ''));
        $all = [];

        if ($type !== 'farm') {
            $all = array_merge($all, $this->multiAccountClusters($filter, $keyword));
        }
        if ($type !== 'multi_account') {
            $all = array_merge($all, $this->farmClusters($filter, $keyword));
        }

        $count = count($all);
        $list = array_slice($all, ($page - 1) * $limit, $limit);

        return compact('list', 'count');
    }

    private function multiAccountClusters(array $filter, string $keyword): array
    {
        $apps = DeviceEnvRiskView::appNameMap();
        $channels = DeviceEnvRiskView::channelMap();
        $rows = User::query()
            ->select('uuid', DB::raw('COUNT(*) as account_count'))
            ->where('uuid', '!=', '')
            ->when(!empty($filter['app_id']), fn ($q) => $q->where('app_id', $filter['app_id']))
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('account_count')
            ->limit(200)
            ->get();

        $uuids = $rows->pluck('uuid')->all();
        $logs = $this->latestLogsByIdentity($uuids);
        $samples = $this->sampleAccountsByIdentity($uuids);
        $clusters = [];
        foreach ($rows as $row) {
            $log = $logs->get($row->uuid);
            if (!$log) {
                continue;
            }
            $formatted = DeviceEnvRiskView::formatLog($log, $apps, $channels);
            $sample = array_slice($samples[$row->uuid] ?? [], 0, 3);
            $blob = strtolower($formatted['device_identity'] . ' ' . $formatted['hardware_hash'] . ' ' . implode(' ', $sample));
            if ($keyword !== '' && !str_contains($blob, $keyword)) {
                continue;
            }
            $clusters[] = [
                'id' => 'ma_' . $formatted['device_identity'],
                'cluster_type' => 'multi_account',
                'title' => '同设备多账号',
                'device_identity' => $formatted['device_identity'],
                'device_id' => $formatted['id'],
                'account_count' => (int) $row->account_count,
                'device_count' => 1,
                'risk_score' => $formatted['risk_score'],
                'hardware_hash' => $formatted['hardware_hash'],
                'sample_accounts' => $sample,
                'last_active_at' => $formatted['last_report_at'],
            ];
        }

        return $clusters;
    }

    private function farmClusters(array $filter, string $keyword): array
    {
        $apps = DeviceEnvRiskView::appNameMap();
        $channels = DeviceEnvRiskView::channelMap();
        $groups = $this->farmClusterQuery($filter)
            ->addSelect([
                DB::raw('MAX(id) as last_id'),
                DB::raw('MAX(risk_score) as max_score'),
                DB::raw('MAX(created_at) as last_active_at'),
            ])
            ->orderByDesc('device_count')
            ->limit(100)
            ->get();

        $lastIds = $groups->pluck('last_id')->filter()->unique()->values()->all();
        $logs = $lastIds === []
            ? collect()
            : RiskProbeLog::query()
                ->whereIn('id', $lastIds)
                ->get(RiskProbeLog::adminListColumns())
                ->keyBy('id');

        $identities = [];
        foreach ($logs as $log) {
            $identity = DeviceEnvRiskView::identityFromLog($log);
            if ($identity !== '') {
                $identities[] = $identity;
            }
        }
        $samples = $this->sampleAccountsByIdentity($identities);
        $accountCounts = $this->accountCountMap($identities);

        $clusters = [];
        foreach ($groups as $group) {
            $ip = $group->client_ip;
            $log = $logs->get((int) $group->last_id);
            $formatted = $log ? DeviceEnvRiskView::formatLog($log, $apps, $channels) : null;
            $identity = $formatted['device_identity'] ?? '';
            $accounts = $identity !== '' ? ($samples[$identity] ?? []) : [];
            $sample = array_values(array_unique(array_slice($accounts, 0, 3)));
            $hardware = $formatted['hardware_hash'] ?? '';
            $blob = strtolower($ip . ' ' . $hardware . ' ' . implode(' ', $sample));
            if ($keyword !== '' && !str_contains($blob, $keyword)) {
                continue;
            }
            $clusters[] = [
                'id' => 'farm_' . md5((string) $ip),
                'cluster_type' => 'farm',
                'title' => '疑似设备农场',
                'device_identity' => '',
                'device_id' => 0,
                'account_count' => $identity === '' ? 0 : ($accountCounts[$identity][(int) ($formatted['app_id'] ?? 0)] ?? 0),
                'device_count' => (int) $group->device_count,
                'risk_score' => (int) ($group->max_score ?? ($formatted['risk_score'] ?? 0)),
                'hardware_hash' => $hardware ?: (string) $ip,
                'sample_accounts' => $sample,
                'last_active_at' => DeviceEnvRiskView::formatTime($group->last_active_at ?? ($formatted['last_report_at'] ?? '')),
            ];
        }

        return $clusters;
    }

    private function farmClusterQuery(array $filter)
    {
        $query = $this->dao->search($filter)
            ->whereNotNull('client_ip')
            ->where('client_ip', '!=', '')
            ->whereNotNull('device_identity')
            ->where('device_identity', '!=', '');

        if (empty($filter['time'])) {
            $query->where('created_at', '>=', now()->subDays(7)->startOfDay()->toDateTimeString());
        }

        return $query
            ->select('client_ip', DB::raw('COUNT(DISTINCT device_identity) as device_count'))
            ->groupBy('client_ip')
            ->havingRaw('COUNT(DISTINCT device_identity) >= 3');
    }

    private function similarDevices(RiskProbeLog $log, array $apps, array $channels): array
    {
        if (!$log->client_ip) {
            return [];
        }
        $identity = DeviceEnvRiskView::identityFromLog($log);
        $rows = RiskProbeLog::query()
            ->where('status', 'ok')
            ->where('client_ip', $log->client_ip)
            ->where(function ($q) use ($identity) {
                $q->where('user_uuid', '!=', $identity)->orWhereNull('user_uuid');
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get(RiskProbeLog::adminListColumns());

        $seen = [];
        $similar = [];
        foreach ($rows as $row) {
            $item = DeviceEnvRiskView::formatLog($row, $apps, $channels);
            if ($item['device_identity'] === $identity || isset($seen[$item['device_identity']])) {
                continue;
            }
            $seen[$item['device_identity']] = $item;
            if (count($seen) >= 8) {
                break;
            }
        }
        $accountCounts = $this->accountCountMap(array_keys($seen));
        foreach ($seen as $item) {
            $item['similarity'] = 80;
            $item['relation'] = '同出口 IP';
            $item['account_count'] = $accountCounts[$item['device_identity']][$item['app_id']] ?? 0;
            $similar[] = $item;
        }

        return $similar;
    }

    private function findDeviceLog($key): ?RiskProbeLog
    {
        if (is_numeric($key) && (int) $key > 0) {
            $byId = RiskProbeLog::query()->find((int) $key);
            if ($byId) {
                return $byId;
            }
        }
        $key = (string) $key;

        return RiskProbeLog::query()
            ->where('status', 'ok')
            ->where('device_identity', $key)
            ->orderByDesc('id')
            ->first();
    }

    private function accounts(string $identity, int $appId = 0): array
    {
        if ($identity === '') {
            return [];
        }
        $query = User::query()->where('uuid', $identity)->where('is_del', 0);
        if ($appId) {
            $query->where('app_id', $appId);
        }
        $apps = DeviceEnvRiskView::appNameMap();
        $channels = DeviceEnvRiskView::channelMap();
        $list = [];
        foreach ($query->orderByDesc('id')->limit(50)->get() as $user) {
            $list[] = [
                'user_id' => (int) $user->id,
                'account' => $user->account,
                'app_id' => (int) $user->app_id,
                'app_name' => $apps[$user->app_id] ?? '',
                'market_channel' => $channels[$user->market_channel] ?? $user->market_channel,
                'bind_at' => DeviceEnvRiskView::formatTime($user->reg_time ?? $user->create_time),
                'last_active' => DeviceEnvRiskView::formatTime($user->last_time),
            ];
        }

        return $list;
    }

    private function accountCountMap(array $identities): array
    {
        $identities = array_values(array_unique(array_filter($identities)));
        if ($identities === []) {
            return [];
        }

        $map = [];
        $rows = User::query()
            ->select('uuid', 'app_id', DB::raw('COUNT(*) as account_count'))
            ->whereIn('uuid', $identities)
            ->where('is_del', 0)
            ->groupBy('uuid', 'app_id')
            ->get();
        foreach ($rows as $row) {
            $map[$row->uuid][(int) $row->app_id] = (int) $row->account_count;
        }

        return $map;
    }

    private function firstSeenMap(array $identities): array
    {
        $identities = array_values(array_unique(array_filter($identities)));
        if ($identities === []) {
            return [];
        }

        return RiskProbeLog::query()
            ->where('status', 'ok')
            ->whereIn('device_identity', $identities)
            ->select('device_identity', DB::raw('MIN(created_at) as first_at'))
            ->groupBy('device_identity')
            ->pluck('first_at', 'device_identity')
            ->all();
    }

    private function latestLogsByIdentity(array $identities)
    {
        $identities = array_values(array_unique(array_filter($identities)));
        if ($identities === []) {
            return collect();
        }

        $columns = RiskProbeLog::adminListColumns();
        $columnSql = implode(', ', $columns);
        $logs = collect();
        foreach (array_chunk($identities, 50) as $chunk) {
            $identitySql = implode(' UNION ALL ', array_fill(0, count($chunk), 'SELECT ? AS device_identity'));
            $rows = DB::select(
                "SELECT {$columnSql} FROM ({$identitySql}) ids JOIN LATERAL (
                    SELECT {$columnSql}
                    FROM risk_probe_logs
                    WHERE status = 'ok' AND device_identity = ids.device_identity
                    ORDER BY id DESC
                    LIMIT 1
                ) latest ON TRUE",
                $chunk
            );
            foreach ($rows as $row) {
                $logs->push((new RiskProbeLog())->newFromBuilder($row));
            }
        }

        return $logs->keyBy(fn ($log) => DeviceEnvRiskView::identityFromLog($log));
    }

    private function sampleAccountsByIdentity(array $identities, int $limit = 3): array
    {
        $identities = array_values(array_unique(array_filter($identities)));
        if ($identities === []) {
            return [];
        }

        $ranked = User::query()
            ->select('uuid', 'account')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY uuid ORDER BY id DESC) as rn')
            ->whereIn('uuid', $identities)
            ->where('is_del', 0);
        $rows = DB::query()->fromSub($ranked, 'ranked_accounts')->where('rn', '<=', $limit)->get();
        $map = [];
        foreach ($rows as $row) {
            $map[$row->uuid][] = $row->account;
        }

        return $map;
    }

    private function trend(array $filter): array
    {
        $start = today()->subDays(6)->startOfDay();
        $end = today()->endOfDay();
        $rows = $this->dao->search($filter)
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(DISTINCT CASE WHEN risk_score >= 70 THEN device_identity END) as high_risk')
            ->selectRaw('SUM(compliance_mode = 1) as blocked')
            ->groupByRaw('DATE(created_at)')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $dates = [];
        $highRisk = [];
        $blocked = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i)->toDateString();
            $row = $rows->get($day);
            $dates[] = today()->subDays($i)->format('m-d');
            $highRisk[] = (int) ($row->high_risk ?? 0);
            $blocked[] = (int) ($row->blocked ?? 0);
        }

        return [
            'dates' => $dates,
            'high_risk' => $highRisk,
            'blocked' => $blocked,
        ];
    }
}
