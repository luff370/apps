<?php

namespace App\Services\Risk\Ledger;

class ScopeReplay
{
    /**
     * @param  array<int, array{id:int,scope_id:int,ip:string,at:int,active_after:bool}>  $events
     */
    public static function activeIpCount(array $events, int $at): int
    {
        $last = [];
        foreach ($events as $event) {
            if ($event['at'] >= $at) {
                continue;
            }
            $scopeId = $event['scope_id'];
            if (!isset($last[$scopeId]) || $event['id'] > $last[$scopeId]['id']) {
                $last[$scopeId] = $event;
            }
        }
        $ips = [];
        foreach ($last as $event) {
            if ($event['active_after']) {
                $ips[$event['ip']] = true;
            }
        }

        return count($ips);
    }

    /**
     * 净释放按查询时点剔除已撤销的退出事实，再按 IP 去重。一次撤销不会抹掉同 IP 的另一次退出。
     *
     * @param  array<int, array{ip:string,at:int,revoked_at:?int}>  $exits
     */
    public static function netReleasedIps(array $exits, int $start, int $end, int $asOf): int
    {
        $ips = [];
        foreach ($exits as $exit) {
            if ($exit['at'] < $start || $exit['at'] >= $end) {
                continue;
            }
            if ($exit['revoked_at'] !== null && $exit['revoked_at'] <= $asOf) {
                continue;
            }
            $ips[$exit['ip']] = true;
        }

        return count($ips);
    }
}
