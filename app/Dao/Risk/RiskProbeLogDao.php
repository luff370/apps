<?php

namespace App\Dao\Risk;

use App\Dao\BaseDao;
use App\Models\RiskProbeLog;
use App\Services\Risk\Ledger\IpNormalizer;
use App\Support\Services\DeviceEnvRiskView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class RiskProbeLogDao extends BaseDao
{
    protected function setModel(): string
    {
        return RiskProbeLog::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery()->where('status', 'ok');

        if (! empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }
        if (! empty($where['market_channel'])) {
            $query->where('market_channel', $where['market_channel']);
        }
        if (! empty($where['version'])) {
            $query->where('app_version', 'like', '%'.$where['version'].'%');
        }
        if (! empty($where['time'])) {
            $this->searchDate($query, 'created_at', $where['time']);
        }
        if (! empty($where['decision'])) {
            $this->scopeDecision($query, $where['decision']);
        }
        if (! empty($where['risk_level'])) {
            $this->scopeRiskLevel($query, $where['risk_level']);
        }
        if (! empty($where['event_type'])) {
            if ($where['event_type'] === 'guard') {
                $query->where(function (Builder $q) {
                    $q->where('env_allows_ads', false)->orWhere('compliance_mode', 1);
                });
            } elseif ($where['event_type'] === 'report') {
                $query->where(function (Builder $q) {
                    $q->where(function (Builder $inner) {
                        $inner->whereNull('env_allows_ads')->orWhere('env_allows_ads', true);
                    })->where('compliance_mode', 0);
                });
            }
        }
        if (isset($where['keyword']) && (string) $where['keyword'] !== '') {
            $keyword = (string) $where['keyword'];
            switch ($where['keyword_type'] ?? 'all') {
                case 'device': return $query->where('device_identity', $keyword);
                case 'install': return $query->where('device_sn', $keyword);
                case 'ip':
                    $ip = IpNormalizer::normalize($keyword);

                    return $ip === '' ? $query->whereRaw('1 = 0') : $query->where('client_ip', $ip);
                case 'ip_prefix': return $query->where('client_ip', 'like', addcslashes($keyword, '%_\\').'%');
                case 'version': return $query->where('app_version', $keyword);
                case 'reason': return $query->whereJsonContains('risk_reasons', $keyword);
            }
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('user_uuid', $keyword)
                    ->orWhere('device_sn', $keyword)
                    ->orWhere('app_version', 'like', $keyword.'%')
                    ->orWhere('client_ip', 'like', $keyword.'%')
                    ->orWhere('risk_reasons', 'like', '%'.$keyword.'%');
            });
        }

        return $query;
    }

    public function latestDeviceIds(array $where = []): Builder
    {
        $query = $this->search($where);
        $coveredTime = ! empty($where['time'])
            && ! in_array($where['decision'] ?? '', ['pass', 'verify', 'limit'], true)
            && empty($where['keyword']) && empty($where['market_channel'])
            && empty($where['version']) && empty($where['event_type']);
        $index = $coveredTime
            ? (empty($where['app_id']) ? 'rpl_farm_ip_agg' : 'rpl_app_time_agg')
            : $this->snapshotIndex($where);
        if ($index !== null && DB::connection()->getDriverName() === 'mysql') {
            $query->getQuery()->from(DB::raw('`risk_probe_logs` FORCE INDEX (`'.$index.'`)'));
        }

        return $query
            ->where('device_identity', '>', '')
            ->selectRaw('MAX(id) as id')
            ->groupBy('device_identity');
    }

    /** 设备索引覆盖应用、评分和决策；非覆盖条件交给优化器选择。 */
    public function snapshotIndex(array $where): ?string
    {
        foreach (['market_channel', 'version', 'time', 'event_type', 'keyword'] as $key) {
            if (! empty($where[$key])) {
                return null;
            }
        }

        return empty($where['app_id']) ? 'rpl_device_snapshot' : 'rpl_app_device_snapshot';
    }

    public function eventQuery(array $where = [], bool $count = false): Builder
    {
        $query = $this->search($where);
        // Only force a covering index when every predicate is available in it.
        if (DB::connection()->getDriverName() === 'mysql'
            && ($count || ! empty($where['decision']) || ! empty($where['risk_level']))
            && empty($where['keyword']) && empty($where['market_channel'])
            && empty($where['version']) && empty($where['event_type']) && empty($where['time'])) {
            $index = empty($where['app_id']) ? 'rpl_device_snapshot' : 'rpl_app_device_snapshot';
            $query->getQuery()->from(DB::raw('`risk_probe_logs` FORCE INDEX (`'.$index.'`)'));
        }

        return $query;
    }

    public function latestDeviceQuery(array $where = []): Builder
    {
        return $this->newQuery()->whereIn('id', $this->latestDeviceIds($where));
    }

    private function scopeDecision(Builder $query, string $decision): void
    {
        match ($decision) {
            'block' => $query->where('compliance_mode', 1),
            'limit' => $query->where('compliance_mode', 0)->where('ad_switch', 0),
            'verify' => $query->where('compliance_mode', 0)->where('ad_switch', 1)->where('risk_score', '>=', 30),
            'pass' => $query->where('compliance_mode', 0)->where('ad_switch', 1)->where('risk_score', '<', 30),
            default => null,
        };
    }

    private function scopeRiskLevel(Builder $query, string $level): void
    {
        if ($level === 'high') {
            $query->where('risk_score', '>=', 70);

            return;
        }
        $range = DeviceEnvRiskView::levelRange($level);
        if ($range) {
            $query->where('risk_score', '>=', $range[0])->where('risk_score', '<', $range[1]);
        }
    }
}
