<?php

namespace App\Services\Risk;

use App\Models\RiskProbeLog;
use App\Models\SystemApp;
use App\Support\Services\DeviceEnvRiskService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Retire only provably projection-only dispositions. Original facts are never rewritten. */
class ProjectionRiskRepair
{
    public const RULE_ID = 'screen-projection-retirement';

    private const ALLOWED = ['screen_projection', 'env_blocks_ads'];

    public function __construct(private RiskLedgerService $ledger)
    {
    }

    public static function codes($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?? explode('+', $value);
        }

        return is_array($value) ? array_values(array_unique(array_map(fn ($code) => strtolower(trim((string) $code)), $value))) : ['unknown'];
    }

    public static function correctedDecision(RiskProbeLog $log): ?array
    {
        $probe = $log->probe_json;
        if ($log->status !== 'ok' || ! is_array($probe) || ! in_array($probe['screen_projection_active'] ?? null, [true, 1], true)) {
            return null;
        }
        $codes = self::codes($log->risk_reasons);
        $basisCodes = self::codes($probe['_risk_basis']['decision']['reasons'] ?? []);
        if (array_diff($codes, self::ALLOWED) || array_diff($basisCodes, self::ALLOWED) || (int) $log->risk_score > 70) {
            return null;
        }
        $errors = $log->validation_errors ?? [];
        if (! is_array($errors) || $errors !== []) {
            return null;
        }
        $decision = app(DeviceEnvRiskService::class)->reassessProbe($probe, $errors);

        return $decision['score'] === 0 && $decision['reasons'] === [] ? $decision : null;
    }

    private function candidates(int $after, int $until)
    {
        return DB::table('risk_scopes')->where('state', 'active')->where('id', '>', $after)->where('id', '<=', $until)
            ->where(function ($q) {
                $q->where('reason_codes', 'like', '%"screen_projection"%')->orWhereExists(function ($source) {
                    $source->selectRaw('1')->from('risk_probe_logs')->whereColumn('risk_probe_logs.id', 'risk_scopes.source_log_id')
                        ->where('risk_reasons', 'like', '%"screen_projection"%');
                });
            });
    }

    public function run(bool $apply, int $after, int $until, int $limit, int $seconds): array
    {
        $started = microtime(true);
        $until = $until ?: (int) DB::table('risk_scopes')->max('id');
        $report = ['mode' => $apply ? 'apply' : 'dry-run', 'until_id' => $until, 'last_scope_id' => $after,
            'scanned' => 0, 'eligible' => 0, 'released' => 0, 'skipped' => [], 'eligible_sample' => []];
        foreach ($this->candidates($after, $until)->orderBy('id')->limit(max(1, min(1000, $limit)))->get() as $scope) {
            $result = $apply ? DB::transaction(function () use ($scope) {
                $this->ledger->lockIp($scope->ip);
                $current = DB::table('risk_scopes')->where('id', $scope->id)->lockForUpdate()->first();
                $proof = $this->proof($current);
                if ($proof['reason'] === null) {
                    $this->release($current, $proof);
                }

                return $proof;
            }, 3) : $this->proof($scope);
            $report['scanned']++;
            $report['last_scope_id'] = (int) $scope->id;
            if ($result['reason'] === null) {
                $report['eligible']++;
                $report['released'] += (int) $apply;
                if (count($report['eligible_sample']) < 20) {
                    $report['eligible_sample'][] = (int) $scope->id;
                }
            } else {
                $reason = $result['reason'];
                $report['skipped'][$reason] = ($report['skipped'][$reason] ?? 0) + 1;
            }
            if (microtime(true) - $started >= $seconds) {
                break;
            }
        }
        $report['has_more'] = $this->candidates($report['last_scope_id'], $until)->exists();

        return $report;
    }

    private function proof(?object $scope): array
    {
        $skip = fn ($reason) => ['reason' => $reason];
        if (! $scope || $scope->state !== 'active') {
            return $skip('not_active');
        }
        // Read raw persisted codes, never the display normalizer which hides retired signals.
        $codes = self::codes($scope->reason_codes);
        $evidenceCodes = DB::table('risk_reason_evidence')->where('scope_id', $scope->id)->pluck('code')->all();
        if (array_diff(array_merge($codes, $evidenceCodes), self::ALLOWED) || (int) $scope->risk_score > 70) {
            return $skip('other_risk');
        }
        if (! $this->ledger->caughtUp()) {
            return $skip('inbox_pending');
        }
        if (app(RiskReviewEvidence::class)->locked((int) $scope->app_id, (string) $scope->device_identity)) {
            return $skip('hard_block');
        }
        if ($this->listed($scope)) {
            return $skip('manual_list');
        }
        if (app(RiskReleaseEngine::class)->blockers($scope, true) !== []) {
            return $skip('release_blocker_or_unknown_review');
        }
        $events = DB::table('risk_timeline')->where('scope_id', $scope->id)->whereIn('event_type', ['intake', 'reintake', 'risk_hit'])
            ->orderBy('id')->limit(5001)->get(['source_log_id', 'reason_codes']);
        if ($events->isEmpty() || $events->count() > 5000 || ! $scope->source_log_id) {
            return $skip('incomplete_evidence');
        }
        $ids = [(int) $scope->source_log_id];
        foreach ($events as $event) {
            if (! $event->source_log_id || array_diff(self::codes($event->reason_codes), self::ALLOWED)) {
                return $skip('other_or_missing_timeline_evidence');
            }
            $ids[] = (int) $event->source_log_id;
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        $logs = RiskProbeLog::query()->whereIn('id', $ids)->orderBy('id')->get();
        if ($logs->count() !== count($ids)) {
            return $skip('source_missing');
        }
        $projection = false;
        foreach ($logs as $log) {
            if ((int) $log->app_id !== (int) $scope->app_id || \App\Support\Services\DeviceEnvRiskView::identityFromLog($log) !== $scope->device_identity
                || \App\Services\Risk\Ledger\IpNormalizer::normalize($log->client_ip) !== $scope->ip || self::correctedDecision($log) === null) {
                return $skip('source_has_other_or_unknown_risk');
            }
            $projection = $projection || in_array('screen_projection', self::codes($log->risk_reasons), true);
        }
        if (! $projection) {
            return $skip('projection_not_proven');
        }

        return ['reason' => null, 'source_ids' => $ids, 'source_count' => count($ids),
            'source_hash' => hash('sha256', $logs->toJson()), 'before' => [
                'risk_score' => (int) $scope->risk_score, 'risk_level' => $scope->risk_level, 'decision' => $scope->decision,
                'reason_codes' => self::codes($scope->reason_codes), 'basis_hash' => $scope->basis_hash, 'policy_eligible' => (bool) $scope->policy_eligible,
            ]];
    }

    private function listed(object $scope): bool
    {
        $ids = DB::table('risk_scope_accounts')->where('scope_id', $scope->id)->pluck('user_id')->all();
        if ($scope->user_id) {
            $ids[] = $scope->user_id;
        }
        $users = DB::table('users')->where('app_id', $scope->app_id)->whereIn('id', $ids)->get(['account', 'phone']);
        $accounts = array_filter(array_merge($ids, $users->pluck('account')->all(), $users->pluck('phone')->all()));

        return DB::table('risk_lists')->where('status', 1)->whereIn('app_id', [0, (int) $scope->app_id])
            ->where(function ($q) use ($scope, $accounts) {
                $q->where(fn ($x) => $x->where('target_type', 'device_identity')->where('target_value', $scope->device_identity))
                    ->orWhere(fn ($x) => $x->where('target_type', 'install_id')->where('target_value', $scope->install_id))
                    ->orWhere(fn ($x) => $x->where('target_type', 'hardware_hash')->where('target_value', substr(hash('sha256', (string) $scope->install_id), 0, 16)))
                    ->orWhere(fn ($x) => $x->where('target_type', 'account')->whereIn('target_value', $accounts));
            })->exists();
    }

    private function release(object $scope, array $proof): void
    {
        $now = Carbon::now('Asia/Shanghai');
        $releaseId = DB::table('risk_releases')->insertGetId([
            'ip' => $scope->ip, 'released_at' => $now, 'status' => 'released', 'rule_id' => self::RULE_ID, 'rule_version' => '1',
            'resource_version' => 1, 'idempotency_key' => self::RULE_ID.':scope:'.$scope->id,
            'evidence' => json_encode($proof, JSON_UNESCAPED_UNICODE), 'explanation' => '投屏规则下线：核验全部关联原始证据，仅投屏导致的历史评分修正为 0，并解除本范围处置。',
            'ip_fully_released' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('risk_release_scopes')->insert([
            'release_id' => $releaseId, 'scope_id' => $scope->id, 'app_id' => $scope->app_id,
            'app_name' => SystemApp::idToNameMap()[(int) $scope->app_id] ?? '', 'device_identity' => $scope->device_identity,
            'install_id' => $scope->install_id, 'user_id' => $scope->user_id, 'market_channel' => $scope->market_channel,
            'reason_codes' => $scope->reason_codes, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('risk_scopes')->where('id', $scope->id)->update([
            'risk_score' => 0, 'risk_level' => 'normal', 'decision' => 'pass', 'basis_hash' => null,
            'state' => 'released', 'released_at' => $now, 'release_id' => $releaseId, 'updated_at' => $now,
        ]);
        $this->ledger->scopeEvent((int) $scope->id, $scope->ip, 'rule_correction', $now, false, $releaseId);
        // Historical-only active scopes still keep the IP dossier active; never remove an entire shared IP.
        $fully = DB::table('risk_scopes')->where('ip', $scope->ip)->where('state', 'active')->doesntExist();
        if ($fully) {
            DB::table('risk_ip_exits')->insert(['ip' => $scope->ip, 'exited_at' => $now, 'release_id' => $releaseId, 'status' => 'effective', 'created_at' => $now, 'updated_at' => $now]);
        }
        DB::table('risk_releases')->where('id', $releaseId)->update(['ip_fully_released' => (int) $fully]);
        $this->ledger->timeline($scope->ip, 'rule_correction', $now, null, $scope->device_identity, [], 'pass', 'normal', (int) $scope->id,
            '投屏历史处置修正', $releaseId, 'system', '规则修正');
        $this->ledger->refreshDossier($scope->ip);
    }
}
