<?php

namespace App\Services\Risk;

use App\Models\RiskProbeLog;
use App\Models\SystemApp;
use App\Services\Risk\Ledger\IpNormalizer;
use App\Services\Risk\Ledger\ReasonCatalog;
use App\Support\Services\DeviceEnvRiskView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RiskLedgerService
{
    public function sync(int $batch, int $maxSeconds): int
    {
        $started = time();
        $processed = 0;
        do {
            $cursor = $this->cursor();
            $logs = RiskProbeLog::query()
                ->where('id', '>', $cursor)
                ->orderBy('id')
                ->limit($batch)
                ->get(array_merge(RiskProbeLog::adminListColumns(), [
                    'route', 'touch_sample_count', 'click_sample_count', 'swipe_sample_count', 'created_at',
                ]));
            if ($logs->isEmpty()) {
                break;
            }
            foreach ($logs as $log) {
                $this->applyLog($log);
                $this->advanceCursor((int) $log->id);
                $processed++;
                if ((time() - $started) >= $maxSeconds) {
                    return $processed;
                }
            }
        } while ($logs->count() === $batch);

        return $processed;
    }

    public function applyLog(RiskProbeLog $log): void
    {
        $logId = (int) $log->id;
        if ($logId <= 0 || DB::table('risk_ledger_applied')->where('source_log_id', $logId)->exists()) {
            return;
        }

        DB::transaction(function () use ($log, $logId) {
            if (DB::table('risk_ledger_applied')->where('source_log_id', $logId)->lockForUpdate()->exists()) {
                return;
            }
            $occurredAt = Carbon::parse($log->created_at)->timezone('Asia/Shanghai');
            $ip = IpNormalizer::normalize($log->client_ip);
            $decision = DeviceEnvRiskView::decision((int) $log->risk_score, (int) $log->compliance_mode, (int) $log->ad_switch);
            $coverage = $this->coverage($log);
            if ($coverage === 'unknown') {
                $this->markUnclassified($occurredAt->toDateString());
            }
            if ($coverage === 'visit' && $ip !== '') {
                DB::table('risk_visits')->insert([
                    'source_log_id' => $logId,
                    'app_id' => (int) $log->app_id,
                    'ip' => $ip,
                    'occurred_at' => $occurredAt->toDateTimeString(),
                    'decision' => $decision,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $device = DeviceEnvRiskView::identityFromLog($log);
            $risky = in_array($decision, ['verify', 'limit', 'block'], true);
            if ($risky && $ip !== '' && $device !== '') {
                $this->intake($log, $ip, $device, $decision, $occurredAt);
            } elseif ($ip !== '') {
                $this->touchActivity($ip, $occurredAt);
            }

            DB::table('risk_ledger_applied')->insert([
                'source_log_id' => $logId,
                'applied_at' => now(),
            ]);
        });
    }

    public function caughtUp(): bool
    {
        $max = (int) (RiskProbeLog::query()->max('id') ?? 0);

        return $this->cursor() >= $max;
    }

    public function cursor(): int
    {
        return (int) (DB::table('risk_ledger_meta')->where('id', 1)->value('cursor_id') ?? 0);
    }

    private function advanceCursor(int $id): void
    {
        $current = $this->cursor();
        if ($id <= $current) {
            return;
        }
        DB::table('risk_ledger_meta')->updateOrInsert(
            ['id' => 1],
            ['cursor_id' => $id, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function coverage(RiskProbeLog $log): string
    {
        if ($log->status !== 'ok') {
            return 'skip';
        }
        $known = $log->touch_sample_count !== null || $log->click_sample_count !== null || $log->swipe_sample_count !== null;
        $samples = (int) $log->touch_sample_count + (int) $log->click_sample_count + (int) $log->swipe_sample_count;
        $route = strtolower((string) $log->route);
        if ($samples > 0 || str_contains($route, 'login') || str_contains($route, 'sign')) {
            return 'visit';
        }

        return $known ? 'skip' : 'unknown';
    }

    private function markUnclassified(string $day): void
    {
        $row = DB::table('risk_day_flags')->where('day', $day)->lockForUpdate()->first();
        if ($row) {
            DB::table('risk_day_flags')->where('day', $day)->update([
                'unclassified_logs' => (int) $row->unclassified_logs + 1,
            ]);

            return;
        }
        DB::table('risk_day_flags')->insert(['day' => $day, 'unclassified_logs' => 1]);
    }

    private function intake(RiskProbeLog $log, string $ip, string $device, string $decision, Carbon $occurredAt): void
    {
        $appId = (int) $log->app_id;
        $reasons = ReasonCatalog::normalizeList($log->risk_reasons);
        $level = DeviceEnvRiskView::level((int) $log->risk_score);
        if (in_array($decision, ['limit', 'block'], true) && !in_array($level, ['high', 'critical'], true)) {
            $level = 'high';
        }
        $scope = DB::table('risk_scopes')
            ->where('app_id', $appId)
            ->where('device_identity', $device)
            ->where('ip', $ip)
            ->where('state', 'active')
            ->lockForUpdate()
            ->first();

        if ($scope) {
            $codes = array_values(array_unique(array_merge($this->codes($scope->reason_codes), $reasons)));
            DB::table('risk_scopes')->where('id', $scope->id)->update([
                'reason_codes' => json_encode($codes, JSON_UNESCAPED_UNICODE),
                'risk_level' => $this->higherLevel((string) $scope->risk_level, $level),
                'decision' => $this->stricter((string) $scope->decision, $decision),
                'risk_score' => max((int) $scope->risk_score, (int) $log->risk_score),
                'market_channel' => $log->market_channel ?: $scope->market_channel,
                'version_name' => $log->app_version ?: $scope->version_name,
                'evidence_after_hold_at' => $scope->auto_release_hold_version ? $occurredAt->toDateTimeString() : $scope->evidence_after_hold_at,
                'updated_at' => now(),
            ]);
            $this->timeline($ip, 'risk_hit', $occurredAt, $log, $device, $codes, $decision, $level, (int) $scope->id, '风险命中');
            $this->refreshDossier($ip);

            return;
        }

        $scopeId = DB::table('risk_scopes')->insertGetId([
            'app_id' => $appId,
            'ip' => $ip,
            'device_identity' => $device,
            'install_id' => $log->device_sn ?: $device,
            'user_id' => null,
            'market_channel' => $log->market_channel,
            'version_name' => $log->app_version,
            'reason_codes' => json_encode($reasons, JSON_UNESCAPED_UNICODE),
            'risk_level' => $level === 'normal' ? 'watch' : $level,
            'decision' => $decision,
            'risk_score' => (int) $log->risk_score,
            'state' => 'active',
            'collected_at' => $occurredAt->toDateTimeString(),
            'source_log_id' => (int) $log->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('risk_intakes')->insert([
            'scope_id' => $scopeId,
            'app_id' => $appId,
            'ip' => $ip,
            'device_identity' => $device,
            'user_id' => null,
            'collected_at' => $occurredAt->toDateTimeString(),
            'source_log_id' => (int) $log->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $previous = DB::table('risk_scopes')
            ->where('app_id', $appId)
            ->where('device_identity', $device)
            ->where('ip', $ip)
            ->where('id', '<>', $scopeId)
            ->exists();
        $type = $previous ? 'reintake' : 'intake';
        $this->scopeEvent($scopeId, $ip, $type, $occurredAt, true, null);
        $this->timeline($ip, $type, $occurredAt, $log, $device, $reasons, $decision, $level, $scopeId, $previous ? '重新收录' : '收录入名单');
        $this->refreshDossier($ip);
    }

    public function refreshDossier(string $ip): void
    {
        $scopes = DB::table('risk_scopes')->where('ip', $ip)->get();
        if ($scopes->isEmpty()) {
            DB::table('risk_ip_dossiers')->where('ip', $ip)->delete();

            return;
        }
        $apps = SystemApp::idToNameMap();
        $channels = SystemApp::marketChannelsMap();
        $active = $scopes->where('state', 'active')->values();
        $high = $active->contains(function ($scope) {
            return in_array($scope->risk_level, ['high', 'critical'], true)
                || in_array($scope->decision, ['limit', 'block'], true);
        });
        $state = $active->isEmpty() ? 'released' : ($high ? 'high' : 'watch');
        $latestActive = $active->sortByDesc('id')->first();
        $markets = [];
        $appRows = [];
        $tags = [];
        $devices = [];
        $accounts = [];
        foreach ($scopes as $scope) {
            $channel = (string) $scope->market_channel;
            if ($channel !== '' && !isset($markets[$channel])) {
                $markets[$channel] = ['value' => $channel, 'label' => $channels[$channel] ?? $channel];
            }
            $appId = (string) $scope->app_id;
            if ($appId !== '0' && !isset($appRows[$appId])) {
                $appRows[$appId] = ['id' => $appId, 'name' => $apps[(int) $scope->app_id] ?? ('应用 ' . $appId)];
            }
            if ($scope->device_identity !== '') {
                $devices[$scope->device_identity] = true;
            }
            if ($scope->user_id) {
                $accounts[$scope->app_id . ':' . $scope->user_id] = true;
            }
            foreach ($this->codes($scope->reason_codes) as $code) {
                $tags[$code] = [
                    'code' => $code,
                    'label' => ReasonCatalog::label($code),
                    'active' => ($tags[$code]['active'] ?? false) || ($scope->state === 'active' && in_array($code, $this->codes($scope->reason_codes), true)),
                ];
            }
        }
        $collected = $scopes->min('collected_at');
        $last = DB::table('risk_timeline')->where('ip', $ip)->max('occurred_at') ?: $scopes->max('updated_at');
        DB::table('risk_ip_dossiers')->updateOrInsert(['ip' => $ip], [
            'state' => $state,
            'current_decision' => $latestActive->decision ?? $scopes->sortByDesc('id')->first()->decision,
            'active_scope_count' => $active->count(),
            'device_count' => count($devices),
            'account_count' => count($accounts),
            'markets' => json_encode(array_values($markets), JSON_UNESCAPED_UNICODE),
            'apps' => json_encode(array_values($appRows), JSON_UNESCAPED_UNICODE),
            'tags' => json_encode(array_values($tags), JSON_UNESCAPED_UNICODE),
            'collected_at' => $collected,
            'collection_known' => 1,
            'first_risk_at' => $collected,
            'last_activity_at' => $last,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function scopeEvent(int $scopeId, string $ip, string $type, Carbon $at, bool $activeAfter, ?int $releaseId): void
    {
        DB::table('risk_scope_events')->insert([
            'scope_id' => $scopeId,
            'ip' => $ip,
            'event_type' => $type,
            'active_after' => $activeAfter ? 1 : 0,
            'occurred_at' => $at->toDateTimeString(),
            'release_id' => $releaseId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function timeline(
        string $ip,
        string $type,
        Carbon $at,
        ?RiskProbeLog $log,
        string $device,
        array $reasons,
        string $decision,
        string $level,
        ?int $scopeId,
        string $title,
        ?int $releaseId = null,
        ?string $operatorType = null,
        ?string $operatorName = null,
    ): void {
        $sourceId = $log ? (int) $log->id : null;
        if ($sourceId && DB::table('risk_timeline')->where('source_log_id', $sourceId)->where('event_type', $type)->exists()) {
            return;
        }
        $appId = $log ? (int) $log->app_id : 0;
        $names = $appId ? SystemApp::idToNameMap() : [];
        DB::table('risk_timeline')->insert([
            'ip' => $ip,
            'event_type' => $type,
            'occurred_at' => $at->toDateTimeString(),
            'received_at' => $log ? Carbon::parse($log->created_at)->toDateTimeString() : $at->toDateTimeString(),
            'app_id' => $appId,
            'app_name' => $names[$appId] ?? null,
            'version_name' => $log->app_version ?? null,
            'market_channel' => $log->market_channel ?? null,
            'device_identity' => $device !== '' ? $device : null,
            'reason_codes' => json_encode($reasons, JSON_UNESCAPED_UNICODE),
            'decision' => $decision !== '' ? $decision : null,
            'risk_level' => $level !== '' ? $level : null,
            'risk_score' => $log ? (int) $log->risk_score : null,
            'source_log_id' => $sourceId,
            'release_id' => $releaseId,
            'scope_id' => $scopeId,
            'operator_type' => $operatorType,
            'operator_name' => $operatorName,
            'title' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function touchActivity(string $ip, Carbon $at): void
    {
        if (!DB::table('risk_ip_dossiers')->where('ip', $ip)->exists()) {
            return;
        }
        DB::table('risk_ip_dossiers')->where('ip', $ip)->where(function ($query) use ($at) {
            $query->whereNull('last_activity_at')->orWhere('last_activity_at', '<', $at->toDateTimeString());
        })->update(['last_activity_at' => $at->toDateTimeString(), 'updated_at' => now()]);
    }

    public function codes($value): array
    {
        if (is_array($value)) {
            return ReasonCatalog::normalizeList($value);
        }
        $decoded = json_decode((string) $value, true);

        return ReasonCatalog::normalizeList(is_array($decoded) ? $decoded : []);
    }

    private function higherLevel(string $current, string $next): string
    {
        $rank = ['normal' => 0, 'watch' => 1, 'high' => 2, 'critical' => 3];

        return ($rank[$next] ?? 0) > ($rank[$current] ?? 0) ? $next : $current;
    }

    private function stricter(string $current, string $next): string
    {
        $rank = ['pass' => 0, 'verify' => 1, 'limit' => 2, 'block' => 3];

        return ($rank[$next] ?? 0) > ($rank[$current] ?? 0) ? $next : $current;
    }
}
