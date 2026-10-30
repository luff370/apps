<?php

namespace App\Services\Risk;

use App\Services\Risk\Ledger\IpNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Durable activity inbox consumer. No business request invokes this projector. */
class RiskActivityProjector
{
    public function __construct(private RiskLedgerService $ledger, private RiskReleaseEngine $engine)
    {
    }

    public function sync(int $batch = 100, int $maxSeconds = 10): int
    {
        $started = microtime(true);
        $processed = 0;
        do {
            // Do not range-lock the pending index: new reports must remain free to insert.
            $rows = DB::table('risk_activity_events')->whereNull('projected_at')->orderBy('id')->limit(max(1, min(200, $batch)))->get();
            foreach ($rows->groupBy(fn ($event) => $event->app_id.':'.$event->device_identity) as $group) {
                $processed += DB::transaction(function () use ($group) {
                    $count = 0;
                    $ips = [];
                    foreach ($group as $candidate) {
                        $ip = IpNormalizer::normalize((string) $candidate->ip);
                        if ($ip !== '') {
                            $this->ledger->lockIp($ip);
                        }
                        $event = DB::table('risk_activity_events')->where('id', $candidate->id)->lockForUpdate()->first();
                        if (! $event || $event->projected_at !== null) {
                            continue;
                        }
                        $this->project($event, $ip);
                        DB::table('risk_activity_events')->where('id', $event->id)->update(['projected_at' => now()]);
                        if ($ip !== '') {
                            $ips[$ip] = true;
                        }
                        $count++;
                    }
                    foreach (array_keys($ips) as $ip) {
                        $this->ledger->refreshDossier($ip);
                    }
                    if ($count > 0) {
                        $event = $group->first();
                        $this->engine->evaluateDevice((int) $event->app_id, (string) $event->device_identity);
                    }

                    return $count;
                }, 3);
                if (microtime(true) - $started >= $maxSeconds) {
                    return $processed;
                }
            }
        } while ($rows->count() === max(1, min(200, $batch)) && microtime(true) - $started < $maxSeconds);

        return $processed;
    }

    private function project(object $event, string $ip): void
    {
        $appId = (int) $event->app_id;
        $device = (string) $event->device_identity;
        $at = Carbon::parse($event->occurred_at, 'Asia/Shanghai');
        $received = Carbon::parse($event->received_at, 'Asia/Shanghai');
        $scopes = DB::table('risk_scopes')->where('app_id', $appId)->where('device_identity', $device)
            ->where('state', 'active')->where('collected_at', '<=', $event->occurred_at)->get();
        if ($event->user_id !== null) {
            foreach ($scopes as $scope) {
                $this->ledger->linkAccount((int) $scope->id, $appId, (string) $event->user_id, $at);
                if (! $scope->user_id) {
                    DB::table('risk_scopes')->where('id', $scope->id)->update(['user_id' => $event->user_id, 'updated_at' => now()]);
                }
            }
        }
        if ($event->online_verified && $event->interaction) {
            DB::table('risk_scopes')->where('app_id', $appId)->where('device_identity', $device)
                ->where('state', 'active')->whereNotNull('auto_release_hold_version')->where('hold_at', '<', $event->occurred_at)
                ->update(['evidence_after_hold_at' => $event->occurred_at]);
        }
        if (in_array($event->event_type, ['login', 'access', 'interaction'], true)) {
            $this->ledger->recordAccess((string) $event->event_id, $appId, $device, $event->user_id, $ip,
                (string) $event->version_name, (string) $event->market_channel,
                $event->event_type === 'login' ? 'login' : 'access', (string) $event->decision, $at, $received);
            if ($ip !== '' && DB::table('risk_ip_dossiers')->where('ip', $ip)->exists()) {
                DB::table('risk_timeline')->insertOrIgnore([
                    'activity_event_id' => $event->id, 'ip' => $ip, 'event_type' => 'login',
                    'occurred_at' => $event->occurred_at, 'received_at' => $event->received_at,
                    'app_id' => $appId, 'version_name' => $event->version_name, 'market_channel' => $event->market_channel,
                    'device_identity' => $device, 'user_id' => $event->user_id, 'decision' => $event->decision,
                    'title' => $event->event_type === 'login' ? '登录' : '访问', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        // Includes pure heartbeat visits for existing dossiers; never creates a risk dossier for a safe IP.
        if ($ip !== '') {
            $this->ledger->touchActivity($ip, $at);
        }
    }
}
