<?php

namespace App\Services\Risk;

use App\Support\Services\ClientRequestContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityEventService
{
    public function __construct(private RiskReleaseEngine $engine)
    {
    }

    public function ingest(Request $request): array
    {
        $appId = (int) (ClientRequestContext::appId($request) ?: 0);
        // 与 risk_probe_logs.device_identity / 台账保持 UUID 优先、Device-Sn 兜底。
        $device = (string) (ClientRequestContext::uuid($request) ?: ClientRequestContext::deviceSn($request) ?: '');
        if ($appId <= 0 || $device === '') {
            return ['accepted' => 0, 'duplicated' => 0, 'rejected' => 1, 'message' => '缺少可信的应用或设备身份'];
        }
        $events = $request->input('events', []);
        if (!is_array($events) || $events === []) {
            return ['accepted' => 0, 'duplicated' => 0, 'rejected' => 0];
        }
        $userId = '';
        try {
            $uid = (int) authUserId();
            $userId = $uid > 0 ? (string) $uid : '';
        } catch (\Throwable $e) {
            $userId = '';
        }
        $received = Carbon::now('Asia/Shanghai');
        $accepted = 0;
        $duplicated = 0;
        $rejected = 0;
        foreach (array_slice($events, 0, 100) as $event) {
            if (!is_array($event)) {
                $rejected++;
                continue;
            }
            $eventId = trim((string) ($event['event_id'] ?? ''));
            $sessionId = trim((string) ($event['session_id'] ?? ''));
            $occurred = $event['occurred_at'] ?? null;
            if ($eventId === '' || $sessionId === '' || !is_numeric($occurred)) {
                $rejected++;
                continue;
            }
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
            DB::table('risk_activity_events')->insert([
                'event_id' => $eventId,
                'app_id' => $appId,
                'device_identity' => $device,
                'install_id' => (string) (ClientRequestContext::deviceSn($request) ?: $device),
                'session_id' => $sessionId,
                'user_id' => $userId !== '' ? $userId : null,
                'version_name' => ClientRequestContext::appVersion($request),
                'version_code' => (string) ($event['version_code'] ?? ''),
                'market_channel' => ClientRequestContext::marketChannel($request),
                'occurred_at' => $occurredAt->toDateTimeString(),
                'received_at' => $received->toDateTimeString(),
                'foreground' => !empty($event['foreground']) ? 1 : 0,
                'screen_interactive' => !empty($event['screen_interactive']) ? 1 : 0,
                'interaction' => !empty($event['interaction']) ? 1 : 0,
                'event_seq' => (int) ($event['event_seq'] ?? 0),
                'ip' => (string) $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            if ($userId !== '') {
                DB::table('risk_scopes')
                    ->where('app_id', $appId)
                    ->where('device_identity', $device)
                    ->where('state', 'active')
                    ->where(function ($query) {
                        $query->whereNull('user_id')->orWhere('user_id', '');
                    })
                    ->update(['user_id' => $userId, 'updated_at' => now()]);
            }
            $accepted++;
        }
        if ($accepted > 0) {
            $this->engine->evaluateDevice($appId, $device);
        }

        return ['accepted' => $accepted, 'duplicated' => $duplicated, 'rejected' => $rejected];
    }
}
