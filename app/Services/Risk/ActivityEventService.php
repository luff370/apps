<?php

namespace App\Services\Risk;

use App\Support\Services\ClientRequestContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityEventService
{
    public function ingest(Request $request): array
    {
        $appId = (int) (ClientRequestContext::appId($request) ?: 0);
        // 与 risk_probe_logs.device_identity / 台账保持 UUID 优先、Device-Sn 兜底。
        $device = (string) (ClientRequestContext::uuid($request) ?: ClientRequestContext::deviceSn($request) ?: '');
        if ($appId <= 0 || $device === '') {
            return ['accepted' => 0, 'duplicated' => 0, 'rejected' => 1, 'message' => '缺少可信的应用或设备身份'];
        }
        $events = $request->input('events', []);
        if (! is_array($events) || $events === []) {
            return ['accepted' => 0, 'duplicated' => 0, 'rejected' => 0];
        }
        $userId = RiskIdentity::userId($request);
        $received = Carbon::now('Asia/Shanghai');
        $accepted = 0;
        $duplicated = 0;
        $rejected = 0;
        foreach (array_slice($events, 0, 100) as $event) {
            if (! is_array($event)) {
                $rejected++;

                continue;
            }
            $eventId = trim((string) ($event['event_id'] ?? ''));
            $sessionId = trim((string) ($event['session_id'] ?? ''));
            $occurred = $event['occurred_at'] ?? null;
            if ($eventId === '' || strlen($eventId) > 128 || $sessionId === '' || strlen($sessionId) > 128 || ! is_numeric($occurred)) {
                $rejected++;

                continue;
            }
            $eventId = hash('sha256', $appId.':'.$device.':'.$eventId);
            if (DB::table('risk_activity_events')->where('event_id', $eventId)->exists()) {
                $duplicated++;

                continue;
            }
            $stamp = (int) $occurred;
            if ($stamp > 20000000000) {
                $stamp = (int) floor($stamp / 1000);
            }
            $occurredAt = Carbon::createFromTimestamp($stamp)->timezone('Asia/Shanghai');
            if ($occurredAt->greaterThan($received->copy()->addMinutes(5)) || $occurredAt->lessThan($received->copy()->subDays(30))) {
                $rejected++;

                continue;
            }
            $sequence = filter_var($event['event_seq'] ?? null, FILTER_VALIDATE_INT);
            $monotonic = filter_var($event['monotonic_elapsed_ms'] ?? null, FILTER_VALIDATE_INT);
            $online = ($request->attributes->get('device_env_risk')['status'] ?? '') === 'ok'
                && $sequence !== false && $sequence > 0 && $monotonic !== false && $monotonic >= 0
                && abs($received->timestamp - $occurredAt->timestamp) <= \App\Services\Risk\Ledger\ReleaseRule::ONLINE_SKEW_SECONDS;
            $kind = in_array($event['event_type'] ?? '', ['login', 'access', 'interaction', 'foreground', 'background', 'heartbeat'], true)
                ? $event['event_type'] : 'activity';
            if ($kind === 'login' && $userId === null) {
                $kind = 'access';
            }
            $risk = $request->attributes->get('device_env_risk', []);
            $decision = ($risk['status'] ?? '') === 'ok' ? \App\Support\Services\DeviceEnvRiskView::decision(
                (int) ($risk['score'] ?? 0), (int) ($risk['decision']['compliance_mode'] ?? 0), (int) ($risk['decision']['ad_switch'] ?? 1)) : 'unknown';
            $eventRow = DB::table('risk_activity_events')->insertOrIgnore([
                'event_id' => $eventId,
                'app_id' => $appId,
                'device_identity' => $device,
                'install_id' => (string) (ClientRequestContext::deviceSn($request) ?: $device),
                'session_id' => $sessionId,
                'user_id' => $userId,
                'online_verified' => $online,
                'monotonic_elapsed_ms' => $monotonic === false ? null : $monotonic,
                'event_type' => $kind,
                'decision' => $decision,
                'version_name' => ClientRequestContext::appVersion($request),
                'version_code' => (string) ($event['version_code'] ?? ''),
                'market_channel' => ClientRequestContext::marketChannel($request),
                'occurred_at' => $occurredAt->toDateTimeString(),
                'received_at' => $received->toDateTimeString(),
                'foreground' => filter_var($event['foreground'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'screen_interactive' => filter_var($event['screen_interactive'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'interaction' => filter_var($event['interaction'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                'event_seq' => (int) ($event['event_seq'] ?? 0),
                'ip' => (string) $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if (! $eventRow) {
                $duplicated++;

                continue;
            }
            $accepted++;
        }

        if ($accepted > 0) {
            app(RiskWorkDispatcher::class)->activity();
        }

        return ['accepted' => $accepted, 'duplicated' => $duplicated, 'rejected' => $rejected];
    }
}
