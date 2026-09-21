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
        $base = $this->dao->search($filter);
        $identity = DeviceEnvRiskView::IDENTITY_SQL;
        $todayStart = today()->startOfDay()->toDateTimeString();
        $todayEnd = today()->endOfDay()->toDateTimeString();

        $deviceTotal = (int) (clone $base)->whereRaw($identity . ' IS NOT NULL')
            ->selectRaw('COUNT(DISTINCT ' . $identity . ') as aggregate')
            ->value('aggregate');
        $highRisk = (clone $this->dao->latestDeviceQuery($filter))->where('risk_score', '>=', 70)->count();
        $blockedToday = (clone $this->dao->search($filter))
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->where('compliance_mode', 1)
            ->count();
        $eventToday = (clone $this->dao->search($filter))
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $multiAccount = (int) User::query()
            ->select('uuid')
            ->where('uuid', '!=', '')
            ->when(!empty($filter['app_id']), fn ($q) => $q->where('app_id', $filter['app_id']))
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) >= 2')
            ->get()
            ->count();

        $farmSuspect = $this->farmClusterQuery($filter)->get()->count();

        $latest = $this->dao->latestDeviceQuery($filter);
        $scoreDistribution = [
            ['level' => 'normal', 'label' => '正常 0-30', 'count' => (clone $latest)->where('risk_score', '<', 30)->count()],
            ['level' => 'watch', 'label' => '观察 30-70', 'count' => (clone $latest)->where('risk_score', '>=', 30)->where('risk_score', '<', 70)->count()],
            ['level' => 'high', 'label' => '高风险 70-90', 'count' => (clone $latest)->where('risk_score', '>=', 70)->where('risk_score', '<', 90)->count()],
            ['level' => 'critical', 'label' => '严重 90-100', 'count' => (clone $latest)->where('risk_score', '>=', 90)->count()],
        ];

        $decisionBase = $this->dao->latestDeviceQuery($filter);
        $decisionDistribution = [
            ['decision' => 'pass', 'count' => (clone $decisionBase)->where('compliance_mode', 0)->where('ad_switch', 1)->where('risk_score', '<', 30)->count()],
            ['decision' => 'verify', 'count' => (clone $decisionBase)->where('compliance_mode', 0)->where('ad_switch', 1)->where('risk_score', '>=', 30)->count()],
            ['decision' => 'limit', 'count' => (clone $decisionBase)->where('compliance_mode', 0)->where('ad_switch', 0)->count()],
            ['decision' => 'block', 'count' => (clone $decisionBase)->where('compliance_mode', 1)->count()],
        ];

        $apps = DeviceEnvRiskView::appNameMap();
        $channels = DeviceEnvRiskView::channelMap();
        $topLogs = $this->dao->latestDeviceQuery($filter)->orderByDesc('risk_score')->orderByDesc('id')->limit(8)->get();
        $topRiskDevices = [];
        foreach ($topLogs as $log) {
            $row = DeviceEnvRiskView::formatLog($log, $apps, $channels);
            $row['account_count'] = $this->accountCount($row['device_identity'], $row['app_id']);
            $topRiskDevices[] = $row;
        }

        return [
            'summary' => [
                'device_total' => $deviceTotal,
                'high_risk' => $highRisk,
                'blocked_today' => $blockedToday,
                'farm_suspect' => $farmSuspect,
                'multi_account' => $multiAccount,
                'event_today' => $eventToday,
            ],
            'score_distribution' => $scoreDistribution,
            'decision_distribution' => $decisionDistribution,
            'top_risk_devices' => $topRiskDevices,
            'trend' => $this->trend($filter),
        ];
    }

    public function deviceList(array $filter): array
    {
        [$page, $limit] = $this->getPageValue();
        $query = $this->dao->latestDeviceQuery($filter);
        $count = (clone $query)->count();
        $list = [];
        if ($count > 0) {
            $rows = (clone $query)->orderByDesc('id')->forPage($page, $limit)->get();
            $apps = DeviceEnvRiskView::appNameMap();
            $channels = DeviceEnvRiskView::channelMap();
            foreach ($rows as $log) {
                $item = DeviceEnvRiskView::formatLog($log, $apps, $channels);
                $item['account_count'] = $this->accountCount($item['device_identity'], $item['app_id']);
                $item['created_at'] = DeviceEnvRiskView::formatTime(
                    RiskProbeLog::query()
                        ->where(function ($q) use ($item) {
                            $q->where('user_uuid', $item['device_identity'])
                                ->orWhere('device_sn', $item['device_identity']);
                        })
                        ->min('created_at')
                );
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
        $firstAt = RiskProbeLog::query()
            ->where(function ($q) use ($identity) {
                $q->where('user_uuid', $identity)->orWhere('device_sn', $identity);
            })
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
        $events = RiskProbeLog::query()
            ->where('status', 'ok')
            ->where(function ($q) use ($identity) {
                $q->where('user_uuid', $identity)->orWhere('device_sn', $identity);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();
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
            $rows = (clone $query)->orderByDesc('id')->forPage($page, $limit)->get();
            foreach ($rows as $log) {
                $item = DeviceEnvRiskView::formatLog($log, $apps, $channels);
                $user = User::query()->where('uuid', $item['device_identity'])->orderByDesc('id')->first(['id']);
                $item['user_id'] = (int) ($user->id ?? 0);
                $list[] = $item;
            }
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
        $rows = User::query()
            ->select('uuid', DB::raw('COUNT(*) as account_count'), DB::raw('MAX(id) as last_user_id'))
            ->where('uuid', '!=', '')
            ->when(!empty($filter['app_id']), fn ($q) => $q->where('app_id', $filter['app_id']))
            ->groupBy('uuid')
            ->havingRaw('COUNT(*) >= 2')
            ->orderByDesc('account_count')
            ->limit(200)
            ->get();

        $clusters = [];
        foreach ($rows as $row) {
            $log = $this->findDeviceLog($row->uuid);
            if (!$log) {
                continue;
            }
            $formatted = DeviceEnvRiskView::formatLog($log, $apps, DeviceEnvRiskView::channelMap());
            $accounts = $this->accounts($row->uuid, (int) $formatted['app_id']);
            $sample = array_slice(array_column($accounts, 'account'), 0, 3);
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
        $groups = $this->farmClusterQuery($filter)->limit(100)->get();
        $clusters = [];
        foreach ($groups as $group) {
            $ip = $group->client_ip;
            $sampleLogs = $this->dao->search($filter)
                ->where('client_ip', $ip)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
            $identities = [];
            $accounts = [];
            $maxScore = 0;
            $lastActive = '';
            $hardware = '';
            foreach ($sampleLogs as $log) {
                $row = DeviceEnvRiskView::formatLog($log, $apps, DeviceEnvRiskView::channelMap());
                $identities[$row['device_identity']] = $row;
                $maxScore = max($maxScore, $row['risk_score']);
                $lastActive = $lastActive ?: $row['last_report_at'];
                $hardware = $hardware ?: $row['hardware_hash'];
            }
            foreach (array_slice(array_values($identities), 0, 3) as $row) {
                foreach ($this->accounts($row['device_identity'], (int) $row['app_id']) as $acc) {
                    $accounts[] = $acc['account'];
                }
            }
            $sample = array_values(array_unique(array_slice($accounts, 0, 3)));
            $blob = strtolower($ip . ' ' . $hardware . ' ' . implode(' ', $sample));
            if ($keyword !== '' && !str_contains($blob, $keyword)) {
                continue;
            }
            $clusters[] = [
                'id' => 'farm_' . md5($ip),
                'cluster_type' => 'farm',
                'title' => '疑似设备农场',
                'device_identity' => '',
                'device_id' => 0,
                'account_count' => count(array_unique($accounts)),
                'device_count' => (int) $group->device_count,
                'risk_score' => $maxScore,
                'hardware_hash' => $hardware ?: $ip,
                'sample_accounts' => $sample,
                'last_active_at' => $lastActive,
            ];
        }

        return $clusters;
    }

    private function farmClusterQuery(array $filter)
    {
        $identity = DeviceEnvRiskView::IDENTITY_SQL;

        return $this->dao->search($filter)
            ->select('client_ip', DB::raw('COUNT(DISTINCT ' . $identity . ') as device_count'))
            ->whereNotNull('client_ip')
            ->where('client_ip', '!=', '')
            ->whereRaw($identity . ' IS NOT NULL')
            ->groupBy('client_ip')
            ->havingRaw('COUNT(DISTINCT ' . $identity . ') >= 3');
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
            ->get();

        $seen = [];
        $similar = [];
        foreach ($rows as $row) {
            $item = DeviceEnvRiskView::formatLog($row, $apps, $channels);
            if ($item['device_identity'] === $identity || isset($seen[$item['device_identity']])) {
                continue;
            }
            $seen[$item['device_identity']] = true;
            $item['similarity'] = 80;
            $item['relation'] = '同出口 IP';
            $item['account_count'] = $this->accountCount($item['device_identity'], $item['app_id']);
            $similar[] = $item;
            if (count($similar) >= 8) {
                break;
            }
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
            ->where(function ($q) use ($key) {
                $q->where('user_uuid', $key)->orWhere('device_sn', $key);
            })
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

    private function accountCount(string $identity, int $appId = 0): int
    {
        if ($identity === '') {
            return 0;
        }
        $query = User::query()->where('uuid', $identity)->where('is_del', 0);
        if ($appId) {
            $query->where('app_id', $appId);
        }

        return $query->count();
    }

    private function trend(array $filter): array
    {
        $dates = [];
        $highRisk = [];
        $blocked = [];
        $identity = DeviceEnvRiskView::IDENTITY_SQL;
        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $start = $day->copy()->startOfDay()->toDateTimeString();
            $end = $day->copy()->endOfDay()->toDateTimeString();
            $dates[] = $day->format('m-d');
            $dayQuery = $this->dao->search($filter)->whereBetween('created_at', [$start, $end]);
            $highRisk[] = (int) (clone $dayQuery)->where('risk_score', '>=', 70)
                ->selectRaw('COUNT(DISTINCT ' . $identity . ') as aggregate')
                ->value('aggregate');
            $blocked[] = (clone $dayQuery)->where('compliance_mode', 1)->count();
        }

        return [
            'dates' => $dates,
            'high_risk' => $highRisk,
            'blocked' => $blocked,
        ];
    }
}
