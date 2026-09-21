<?php

namespace App\Dao\Risk;

use App\Dao\BaseDao;
use App\Models\RiskProbeLog;
use App\Support\Services\DeviceEnvRiskView;
use Illuminate\Database\Eloquent\Builder;

class RiskProbeLogDao extends BaseDao
{
    protected function setModel(): string
    {
        return RiskProbeLog::class;
    }

    public function search(array $where = []): Builder
    {
        $query = $this->newQuery()->where('status', 'ok');

        if (!empty($where['app_id'])) {
            $query->where('app_id', $where['app_id']);
        }
        if (!empty($where['market_channel'])) {
            $query->where('market_channel', $where['market_channel']);
        }
        if (!empty($where['version'])) {
            $query->where('app_version', 'like', '%' . $where['version'] . '%');
        }
        if (!empty($where['time'])) {
            $this->searchDate($query, 'created_at', $where['time']);
        }
        if (!empty($where['decision'])) {
            $this->scopeDecision($query, $where['decision']);
        }
        if (!empty($where['risk_level'])) {
            $this->scopeRiskLevel($query, $where['risk_level']);
        }
        if (!empty($where['event_type'])) {
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
        if (!empty($where['keyword'])) {
            $keyword = $where['keyword'];
            $query->where(function (Builder $q) use ($keyword) {
                $q->where('user_uuid', $keyword)
                    ->orWhere('device_sn', $keyword)
                    ->orWhere('app_version', 'like', $keyword . '%')
                    ->orWhere('client_ip', 'like', $keyword . '%')
                    ->orWhere('risk_reasons', 'like', '%' . $keyword . '%');
            });
        }

        return $query;
    }

    public function latestDeviceQuery(array $where = []): Builder
    {
        $identity = DeviceEnvRiskView::IDENTITY_SQL;
        $latest = $this->search($where)
            ->selectRaw('MAX(id) as id')
            ->whereRaw($identity . ' IS NOT NULL')
            ->groupByRaw($identity);

        return $this->newQuery()->whereIn('id', $latest);
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
