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
    private array $dirtyIps = [];

    public function sync(int $batch, int $maxSeconds): int
    {
        $started = microtime(true);
        $processed = 0;
        do {
            $count = DB::transaction(function () use ($batch) {
                DB::table('risk_ledger_meta')->insertOrIgnore(['id' => 1, 'cursor_id' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $meta = DB::table('risk_ledger_meta')->where('id', 1)->lockForUpdate()->first();
                $logs = RiskProbeLog::query()->where('id', '>', $meta->cursor_id)->orderBy('id')->limit(max(1, min(500, $batch)))
                    ->get(array_unique(array_merge(RiskProbeLog::adminListColumns(), ['route', 'probe_json', 'touch_sample_count', 'click_sample_count', 'swipe_sample_count'])));
                $this->dirtyIps = [];
                $remaining = $this->applyHistoricalQuiet($logs);
                foreach ($remaining as $log) {
                    $this->applyLog($log, true);
                }
                foreach (array_keys($this->dirtyIps) as $ip) {
                    $this->refreshDossier($ip);
                }
                if ($logs->isNotEmpty()) {
                    $this->advanceCursor((int) $logs->last()->id);
                    if (! $meta->coverage_started_at) {
                        DB::table('risk_ledger_meta')->where('id', 1)->update(['coverage_started_at' => $logs->min('created_at')]);
                    }
                } elseif (! $meta->coverage_started_at) {
                    DB::table('risk_ledger_meta')->where('id', 1)->update(['coverage_started_at' => now(), 'last_success_at' => now()]);
                }

                return $logs->count();
            }, 3);
            $processed += $count;
        } while ($count === max(1, min(500, $batch)) && microtime(true) - $started < $maxSeconds);

        return $processed;
    }

    private function applyHistoricalQuiet($logs): array
    {
        $already = DB::table('risk_ledger_applied')->whereIn('source_log_id', $logs->pluck('id')->all())->pluck('source_log_id')->flip();
        $quiet = [];
        $remaining = [];
        $flags = [];
        $ips = [];
        $review = app(RiskReviewEvidence::class);
        foreach ($logs as $log) {
            if (isset($already[$log->id])) {
                continue;
            }
            $old = Carbon::parse($log->created_at)->lt(Carbon::now('Asia/Shanghai')->subDay());
            $notRisky = $log->status !== 'ok' || ((int) $log->risk_score < 30 && (int) $log->compliance_mode === 0 && (int) $log->ad_switch === 1);
            $coverage = $this->coverage($log);
            if (! $old || ! $notRisky || $coverage === 'visit' || ! $review->canSkipHistorical((int) $log->app_id, (string) $log->created_at)) {
                $remaining[] = $log;

                continue;
            }
            $quiet[] = ['source_log_id' => (int) $log->id, 'applied_at' => now()];
            $at = Carbon::parse($log->created_at)->toDateTimeString();
            if ($coverage === 'unknown') {
                $day = substr($at, 0, 10);
                $flags[$day] = ($flags[$day] ?? 0) + 1;
            }
            $ip = $log->status === 'ok' ? IpNormalizer::normalize($log->client_ip) : '';
            if ($ip !== '') {
                $ips[$ip] = max($ips[$ip] ?? $at, $at);
            }
        }
        if ($quiet === []) {
            return $remaining;
        }
        // The savepoint makes the bulk optimization equivalent even if another consumer claims a row.
        try {
            DB::transaction(function () use ($quiet, $flags, $ips) {
                if (DB::table('risk_ledger_applied')->insertOrIgnore($quiet) !== count($quiet)) {
                    throw new \UnderflowException('Concurrent risk ledger claim');
                }
                foreach ($flags as $day => $count) {
                    DB::table('risk_day_flags')->insertOrIgnore(['day' => $day, 'unclassified_logs' => 0]);
                    DB::table('risk_day_flags')->where('day', $day)->increment('unclassified_logs', $count);
                }
                $existing = DB::table('risk_ip_dossiers')->whereIn('ip', array_keys($ips))->pluck('ip');
                foreach ($existing as $ip) {
                    $this->touchActivity($ip, Carbon::parse($ips[$ip]));
                }
            });
        } catch (\UnderflowException $e) {
            return $logs->reject(fn ($log) => isset($already[$log->id]))->all();
        }

        return $remaining;
    }

    public function applyLog(RiskProbeLog $log, bool $deferDossier = false): void
    {
        $logId = (int) $log->id;
        if ($logId <= 0 || DB::table('risk_ledger_applied')->where('source_log_id', $logId)->exists()) {
            return;
        }

        // 主键插入占位。对不存在的行 SELECT FOR UPDATE 会锁住主键间隙，
        // 相邻 source_log_id 落在同一间隙，随后的 INSERT 互相等待，触发 1213。
        DB::transaction(function () use ($log, $logId, $deferDossier) {
            $claimed = DB::table('risk_ledger_applied')->insertOrIgnore([
                'source_log_id' => $logId,
                'applied_at' => now(),
            ]);
            if ($claimed === 0) {
                return;
            }
            $occurredAt = Carbon::parse($log->created_at)->timezone('Asia/Shanghai');
            $ip = IpNormalizer::normalize($log->client_ip);
            if ($ip !== '') {
                $this->lockIp($ip);
            }
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

            if ($log->status !== 'ok') {
                return;
            }
            $probe = is_array($log->probe_json) ? $log->probe_json : [];
            if (array_key_exists('_candidate_user_id', $probe)) {
                $candidate = $probe['_candidate_user_id'];
                $probe['_user_id'] = $candidate && \App\Models\User::query()->where('id', $candidate)->where('app_id', $log->app_id)->where('is_del', 0)->exists() ? (string) $candidate : null;
                if (isset($probe['_access_event']) && $probe['_access_event']['type'] === 'login' && ! $probe['_user_id']) {
                    $probe['_access_event']['type'] = 'access';
                }
                $log->probe_json = $probe;
            }
            if ($coverage === 'visit') {
                $event = $probe['_access_event'];
                $deviceId = DeviceEnvRiskView::identityFromLog($log);
                $this->recordAccess(hash('sha256', $log->app_id.':'.$deviceId.':'.$event['id']), (int) $log->app_id,
                    $deviceId, $probe['_user_id'] ?? null, $ip, (string) $log->app_version, (string) $log->market_channel,
                    $event['type'], $decision, $occurredAt, $occurredAt);
            }
            $device = DeviceEnvRiskView::identityFromLog($log);
            if ($device !== '') {
                app(RiskReviewEvidence::class)->observe((int) $log->app_id, $device, (string) $log->app_version, (string) $log->market_channel, $occurredAt);
            }
            $risky = in_array($decision, ['verify', 'limit', 'block'], true);
            if ($risky && $coverage !== 'visit') {
                $this->recordAccess('probe:'.$logId, (int) $log->app_id, $device, $probe['_user_id'] ?? null, $ip, (string) $log->app_version, (string) $log->market_channel, 'guard', $decision, $occurredAt, $occurredAt);
            }
            if ($risky && $ip !== '' && $device !== '') {
                $this->intake($log, $ip, $device, $decision, $occurredAt, $deferDossier);
            } elseif ($ip !== '') {
                if ($coverage === 'visit') {
                    $this->timeline($ip, 'login', $occurredAt, $log, $device, [], $decision, DeviceEnvRiskView::level((int) $log->risk_score), null, '访问');
                }
                $this->touchActivity($ip, $occurredAt);
            }
        }, 3);
    }

    public function recordAccess(string $key, int $appId, string $device, ?string $userId, string $ip,
        string $version, string $channel, string $kind, string $decision, Carbon $occurred, Carbon $received): void
    {
        $version = ltrim(trim($version), 'vV');
        $user = $userId ? DB::table('users')->where('id', $userId)->where('app_id', $appId)->first(['account', 'phone']) : null;
        $account = $user->account ?? null;
        if ($userId && $device !== '') {
            $monitors = DB::table('risk_monitor_accounts')->where('status', 1)->whereIn('app_id', [0, $appId])
                ->where(fn ($q) => $q->where('account', $account)->orWhere('account', $user->phone ?? ''))->get();
            foreach ($monitors as $monitor) {
                DB::table('risk_hard_blocks')->insertOrIgnore(['app_id' => $appId, 'device_identity' => $device,
                    'kind' => 'test_account', 'evidence_key' => hash('sha256', 'monitor:'.$appId.':'.$device.':'.$monitor->id),
                    'evidence' => json_encode(['monitor_id' => $monitor->id, 'user_id' => $userId]), 'occurred_at' => $occurred->toDateTimeString()]);
            }
        }
        DB::table('risk_access_facts')->insertOrIgnore(['event_key' => $key, 'app_id' => $appId,
            'device_identity' => $device, 'user_id' => $userId, 'account' => $account, 'phone' => $user->phone ?? null,
            'ip' => $ip ?: null, 'version_name' => $version ?: null, 'market_channel' => $channel ?: null,
            'event_type' => $kind, 'decision' => $decision, 'occurred_at' => $occurred->toDateTimeString(),
            'received_at' => $received->toDateTimeString()]);
    }

    public function lockIp(string $ip): void
    {
        DB::table('risk_ip_locks')->insertOrIgnore(['ip' => $ip]);
        DB::table('risk_ip_locks')->where('ip', $ip)->lockForUpdate()->first();
    }

    public function progress(): array
    {
        $meta = DB::table('risk_ledger_meta')->where('id', 1)->first();
        $source = (int) (RiskProbeLog::query()->max('id') ?? 0);

        $activityPending = DB::table('risk_activity_events')->whereNull('projected_at')->exists();

        return ['activity_pending' => $activityPending, 'cursor_id' => (string) ($meta->cursor_id ?? 0), 'source_max_id' => (string) $source,
            'complete' => (int) ($meta->cursor_id ?? 0) >= $source && ! $activityPending,
            'last_success_at' => $meta->last_success_at ?? null,
            'coverage_started_at' => $meta->coverage_started_at ?? null];
    }

    public function caughtUp(): bool
    {
        $max = (int) (RiskProbeLog::query()->max('id') ?? 0);

        return $this->cursor() >= $max && ! DB::table('risk_activity_events')->whereNull('projected_at')->exists();
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
            ['cursor_id' => $id, 'last_success_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function coverage(RiskProbeLog $log): string
    {
        if ($log->status !== 'ok') {
            return 'skip';
        }
        $probe = is_array($log->probe_json) ? $log->probe_json : [];
        $event = $probe['_access_event'] ?? null;
        if (is_array($event) && ! empty($event['id']) && in_array($event['type'] ?? '', ['login', 'access'], true)) {
            return 'visit';
        }
        if (in_array($probe['event_type'] ?? '', ['heartbeat', 'foreground', 'background'], true)) {
            return 'skip';
        }

        return 'unknown';
    }

    private function markUnclassified(string $day): void
    {
        DB::table('risk_day_flags')->insertOrIgnore(['day' => $day, 'unclassified_logs' => 0]);
        DB::table('risk_day_flags')->where('day', $day)->increment('unclassified_logs');
    }

    private function intake(RiskProbeLog $log, string $ip, string $device, string $decision, Carbon $occurredAt, bool $deferDossier = false): void
    {
        $appId = (int) $log->app_id;
        $probe = is_array($log->probe_json) ? $log->probe_json : [];
        $basisHash = $probe['_risk_basis']['hash'] ?? RiskPolicyService::basis(['score' => (int) $log->risk_score, 'reasons' => $log->risk_reasons ?? [], 'compliance_mode' => (int) $log->compliance_mode, 'ad_switch' => (int) $log->ad_switch]);
        $reasons = ReasonCatalog::normalizeList($log->risk_reasons);
        $level = DeviceEnvRiskView::level((int) $log->risk_score);
        if (in_array($decision, ['limit', 'block'], true) && ! in_array($level, ['high', 'critical'], true)) {
            $level = 'high';
        }
        $eligible = (int) $log->id > (int) DB::table('risk_ledger_meta')->where('id', 1)->value('enforcement_source_id');
        $scope = DB::table('risk_scopes')
            ->where('app_id', $appId)
            ->where('device_identity', $device)
            ->where('ip', $ip)
            ->where('policy_eligible', $eligible)
            ->where('state', 'active')
            ->lockForUpdate()
            ->first();

        if ($scope) {
            if ($occurredAt->lt(Carbon::parse($scope->collected_at))) {
                return;
            }
            $codes = array_values(array_unique(array_merge($this->codes($scope->reason_codes), $reasons)));
            DB::table('risk_scopes')->where('id', $scope->id)->update([
                'basis_hash' => $basisHash,
                'reason_codes' => json_encode($codes, JSON_UNESCAPED_UNICODE),
                'risk_level' => $this->higherLevel((string) $scope->risk_level, $level),
                'decision' => $this->stricter((string) $scope->decision, $decision),
                'risk_score' => max((int) $scope->risk_score, (int) $log->risk_score),
                'market_channel' => $log->market_channel ?: $scope->market_channel,
                'version_name' => $log->app_version ?: $scope->version_name,
                'evidence_after_hold_at' => $scope->evidence_after_hold_at,
                'updated_at' => now(),
            ]);
            if (! empty($probe['_user_id'])) {
                $this->linkAccount((int) $scope->id, $appId, (string) $probe['_user_id'], $occurredAt);
            }
            $this->reasonEvidence((int) $scope->id, $ip, $appId, $device, $reasons, $occurredAt);
            $this->timeline($ip, 'risk_hit', $occurredAt, $log, $device, $codes, $decision, $level, (int) $scope->id, '风险命中');
            if ($deferDossier) {
                $this->dirtyIps[$ip] = true;
            } else {
                $this->refreshDossier($ip);
            }

            return;
        }

        $scopeId = DB::table('risk_scopes')->insertGetId([
            'app_id' => $appId,
            'ip' => $ip,
            'device_identity' => $device,
            'install_id' => $log->device_sn ?: $device,
            'basis_hash' => $basisHash,
            'identity_kind' => $probe['_identity_kind'] ?? 'legacy',
            'policy_eligible' => $eligible,
            'user_id' => ! empty($probe['_user_id']) ? (string) $probe['_user_id'] : null,
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
            'user_id' => ! empty($probe['_user_id']) ? (string) $probe['_user_id'] : null,
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
        if (! empty($probe['_user_id'])) {
            $this->linkAccount($scopeId, $appId, (string) $probe['_user_id'], $occurredAt);
        }
        $this->reasonEvidence($scopeId, $ip, $appId, $device, $reasons, $occurredAt);
        $type = $previous ? 'reintake' : 'intake';
        $this->scopeEvent($scopeId, $ip, $type, $occurredAt, true, null);
        $this->timeline($ip, $type, $occurredAt, $log, $device, $reasons, $decision, $level, $scopeId, $previous ? '重新收录' : '收录入名单');
        if ($deferDossier) {
            $this->dirtyIps[$ip] = true;
        } else {
            $this->refreshDossier($ip);
        }
    }

    public function linkAccount(int $scopeId, int $appId, string $userId, Carbon $at): void
    {
        $old = DB::table('risk_scope_accounts')->where('scope_id', $scopeId)->where('user_id', $userId)->first();
        DB::table('risk_scope_accounts')->upsert([['scope_id' => $scopeId, 'app_id' => $appId, 'user_id' => $userId,
            'first_at' => min($old->first_at ?? $at->toDateTimeString(), $at->toDateTimeString()),
            'last_at' => max($old->last_at ?? $at->toDateTimeString(), $at->toDateTimeString())]], ['scope_id', 'user_id'], ['first_at', 'last_at']);
    }

    private function reasonEvidence(int $scopeId, string $ip, int $appId, string $device, array $codes, Carbon $at): void
    {
        if ($codes === []) {
            return;
        }
        $previous = DB::table('risk_reason_evidence')->where('scope_id', $scopeId)->get()->keyBy('code');
        $values = [];
        foreach ($codes as $code) {
            $old = $previous[$code] ?? null;
            $values[] = ['scope_id' => $scopeId, 'code' => $code, 'ip' => $ip, 'app_id' => $appId,
                'device_identity' => $device, 'hit_count' => (int) ($old->hit_count ?? 0) + 1,
                'first_at' => min($old->first_at ?? $at->toDateTimeString(), $at->toDateTimeString()),
                'last_at' => max($old->last_at ?? $at->toDateTimeString(), $at->toDateTimeString())];
        }
        DB::table('risk_reason_evidence')->upsert($values, ['scope_id', 'code'], ['hit_count', 'first_at', 'last_at']);
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
            if ($channel !== '' && ! isset($markets[$channel])) {
                $markets[$channel] = ['value' => $channel, 'label' => $channels[$channel] ?? $channel];
            }
            $appId = (string) $scope->app_id;
            if ($appId !== '0' && ! isset($appRows[$appId])) {
                $appRows[$appId] = ['id' => $appId, 'name' => $apps[(int) $scope->app_id] ?? ('应用 '.$appId)];
            }
            if ($scope->device_identity !== '') {
                $deviceKey = ($scope->identity_kind ?? 'legacy') === 'device' ? 'device:'.$scope->device_identity : 'app:'.$scope->app_id.':'.$scope->device_identity;
                $devices[$deviceKey] = true;
            }
            if ($scope->user_id) {
                $accounts[$scope->app_id.':'.$scope->user_id] = true;
            }
            foreach ($this->codes($scope->reason_codes) as $code) {
                $tags[$code] = [
                    'code' => $code,
                    'label' => ReasonCatalog::label($code),
                    'active' => ($tags[$code]['active'] ?? false) || ($scope->state === 'active' && in_array($code, $this->codes($scope->reason_codes), true)),
                ];
            }
        }
        $linked = DB::table('risk_scope_accounts as a')->join('risk_scopes as s', 's.id', '=', 'a.scope_id')->where('s.ip', $ip)->get(['a.app_id', 'a.user_id']);
        foreach ($linked as $link) {
            $accounts[$link->app_id.':'.$link->user_id] = true;
        }
        $collected = $scopes->min('collected_at');
        $last = DB::table('risk_timeline')->where('ip', $ip)->max('occurred_at') ?: $scopes->max('updated_at');
        $last = max($last, (string) DB::table('risk_ip_dossiers')->where('ip', $ip)->value('last_activity_at'));
        DB::table('risk_ip_dossiers')->updateOrInsert(['ip' => $ip], [
            'state' => $state,
            'current_decision' => $active->isEmpty() ? 'released' : ($active->contains('decision', 'block') ? 'block' : ($active->contains('decision', 'limit') ? 'limit' : 'verify')),
            'active_scope_count' => $active->count(),
            'device_count' => count($devices),
            'account_count' => count($accounts),
            'markets' => json_encode(array_values($markets), JSON_UNESCAPED_UNICODE),
            'apps' => json_encode(array_values($appRows), JSON_UNESCAPED_UNICODE),
            'tags' => json_encode(array_values($tags), JSON_UNESCAPED_UNICODE),
            'collected_at' => $collected,
            'collection_known' => $scopes->every(fn ($scope) => ($scope->identity_kind ?? 'legacy') !== 'legacy') ? 1 : 0,
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
        $scope = ! $log && $scopeId ? DB::table('risk_scopes')->where('id', $scopeId)->first() : null;
        $appId = $log ? (int) $log->app_id : (int) ($scope->app_id ?? 0);
        $names = $appId ? SystemApp::idToNameMap() : [];
        DB::table('risk_timeline')->insert([
            'ip' => $ip,
            'event_type' => $type,
            'occurred_at' => $at->toDateTimeString(),
            'received_at' => $log ? Carbon::parse($log->created_at)->toDateTimeString() : $at->toDateTimeString(),
            'app_id' => $appId,
            'app_name' => $names[$appId] ?? null,
            'version_name' => $log->app_version ?? $scope->version_name ?? null,
            'market_channel' => $log->market_channel ?? $scope->market_channel ?? null,
            'user_id' => $log ? (($log->probe_json['_user_id'] ?? null) ?: null) : ($scope->user_id ?? null),
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

    public function touchActivity(string $ip, Carbon $at): void
    {
        if (! DB::table('risk_ip_dossiers')->where('ip', $ip)->exists()) {
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
