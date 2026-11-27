<?php

namespace App\Services\Risk;

use App\Models\RiskProbeLog;
use App\Models\SystemApp;
use App\Services\Risk\Ledger\IpNormalizer;
use App\Support\Services\DeviceEnvRiskService;
use App\Support\Services\DeviceEnvRiskView;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Correct dispositions whose original evidence no longer warrants collection. */
class ProjectionRiskRepair
{
    public const RULE_ID = 'screen-projection-retirement';

    public const PAUSED_RULE_ID = 'paused-risk-weights';

    public const RESCORE_EVENT = 'paused_weights_rescore';

    public static function isCorrection(string $rule): bool
    {
        return in_array($rule, [self::RULE_ID, self::PAUSED_RULE_ID], true);
    }

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

        return $decision['score'] === 0 && array_diff($decision['reasons'], self::ALLOWED) === [] ? $decision : null;
    }

    /** Replay an old event with the current weights, retaining the original database row. */
    public static function reassessPausedLog(RiskProbeLog $log): ?array
    {
        $probe = $log->probe_json;
        $codes = self::codes($log->risk_reasons);
        if ($log->status !== 'ok' || ! is_array($probe) || $probe === []
            || ! array_intersect($codes, DeviceEnvRiskService::PAUSED_SCORE_REASONS)
            || array_key_exists('score_items', $probe['_risk_basis']['decision'] ?? [])) {
            return null;
        }

        $decision = app(DeviceEnvRiskService::class)->reassessProbe($probe, $log->validation_errors ?? []);
        $otherCodes = array_diff($codes, DeviceEnvRiskService::PAUSED_SCORE_REASONS);

        return array_diff($otherCodes, $decision['reasons']) ? null : $decision;
    }

    private function candidates(int $after, int $until, bool $paused)
    {
        return DB::table('risk_scopes')->where('state', 'active')->where('id', '>', $after)->where('id', '<=', $until)
            ->where(function ($q) use ($paused) {
                $codes = $paused ? DeviceEnvRiskService::PAUSED_SCORE_REASONS : ['screen_projection'];
                foreach ($codes as $code) {
                    $q->orWhere('reason_codes', 'like', '%"'.$code.'"%');
                }
                if (! $paused) {
                    $q->orWhereExists(function ($source) {
                        $source->selectRaw('1')->from('risk_probe_logs')->whereColumn('risk_probe_logs.id', 'risk_scopes.source_log_id')
                            ->where('risk_reasons', 'like', '%"screen_projection"%');
                    });
                }
            });
    }

    public function run(bool $apply, int $after, int $until, int $limit, int $seconds, bool $paused = false): array
    {
        $started = microtime(true);
        $until = $until ?: (int) DB::table('risk_scopes')->max('id');
        $report = ['mode' => $apply ? 'apply' : 'dry-run', 'until_id' => $until, 'last_scope_id' => $after,
            'scanned' => 0, 'eligible' => 0, 'released' => 0, 'rescore_eligible' => 0, 'rescored' => 0, 'skipped' => [], 'eligible_sample' => []];
        foreach ($this->candidates($after, $until, $paused)->orderBy('id')->limit(max(1, min(1000, $limit)))->get() as $scope) {
            $result = $apply ? DB::transaction(function () use ($scope, $paused) {
                $this->ledger->lockIp($scope->ip);
                $current = DB::table('risk_scopes')->where('id', $scope->id)->lockForUpdate()->first();
                $proof = $this->plan($current, $paused);
                if (isset($proof['rescore'])) {
                    $this->rescore($current, $proof['rescore']);
                } elseif ($proof['reason'] === null) {
                    $this->release($current, $proof, $paused);
                }

                return $proof;
            }, 3) : $this->plan($scope, $paused);
            $report['scanned']++;
            $report['last_scope_id'] = (int) $scope->id;
            if (isset($result['rescore'])) {
                $report['rescore_eligible']++;
                $report['rescored'] += (int) $apply;
            } elseif ($result['reason'] === null) {
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
        $report['has_more'] = $this->candidates($report['last_scope_id'], $until, $paused)->exists();

        return $report;
    }

    private function plan(?object $scope, bool $paused): array
    {
        $proof = $this->proof($scope, $paused);

        return $paused && $proof['reason'] === 'other_risk' ? $this->mixedRescore($scope) : $proof;
    }

    private function mixedRescore(object $scope): array
    {
        if ($scope->review_stage !== 'enforced') {
            return ['reason' => 'reviewed_scope'];
        }
        if (! $this->ledger->caughtUp()) {
            return ['reason' => 'inbox_pending'];
        }
        $events = $this->sourceEvents($scope);
        $ids = $events->pluck('source_log_id')->filter()->unique()->values();
        if ($events->isEmpty() || $events->count() > 5000 || $events->contains('source_log_id', null) || ! $ids->contains($scope->source_log_id)) {
            return ['reason' => 'incomplete_evidence'];
        }
        $logs = RiskProbeLog::query()->whereIn('id', $ids)->orderBy('id')->get();
        if ($logs->count() !== $ids->count()) {
            return ['reason' => 'source_missing'];
        }

        $peak = null;
        $allReasons = [];
        $retired = array_merge(DeviceEnvRiskService::PAUSED_SCORE_REASONS, ['screen_projection']);
        $rank = ['pass' => 0, 'verify' => 1, 'limit' => 2, 'block' => 3];
        $strongest = 'pass';
        $matchingSource = $events->where('risk_score', $scope->risk_score)->last()->source_log_id ?? $scope->source_log_id;
        $matchingFormula = false;
        foreach ($logs as $log) {
            $probe = $log->probe_json;
            if ($log->status !== 'ok' || ! is_array($probe) || $probe === [] || (int) $log->app_id !== (int) $scope->app_id
                || DeviceEnvRiskView::identityFromLog($log) !== $scope->device_identity || IpNormalizer::normalize($log->client_ip) !== $scope->ip) {
                return ['reason' => 'incomplete_evidence'];
            }
            $raw = app(DeviceEnvRiskService::class)->reassessProbe($probe, $log->validation_errors ?? []);
            $old = array_merge(self::codes($log->risk_reasons), self::codes($probe['_risk_basis']['decision']['reasons'] ?? []));
            if (array_diff($old, $retired, $raw['reasons'])) {
                return ['reason' => 'unverified_other_risk'];
            }
            if ((int) $log->id === (int) $matchingSource) {
                $snapshot = $probe['_risk_basis']['decision'] ?? [];
                $matchingFormula = (isset($snapshot['score_items']) ? (int) $snapshot['score'] : $raw['score']) === (int) $scope->risk_score;
            }
            $allReasons = array_merge($allReasons, $raw['reasons']);
            if ($peak === null || $raw['score'] >= $peak['score']) {
                $peak = ['score' => $raw['score'], 'log' => $log];
            }
            $decision = DeviceEnvRiskView::decision($raw['score'], $raw['compliance_mode'], $raw['ad_switch']);
            if ($rank[$decision] > $rank[$strongest]) {
                $strongest = $decision;
            }
        }
        $codes = array_merge(self::codes($scope->reason_codes), DB::table('risk_reason_evidence')->where('scope_id', $scope->id)->pluck('code')->all());
        foreach ($events as $event) {
            $codes = array_merge($codes, self::codes($event->reason_codes));
        }
        if (array_diff($codes, $retired, $allReasons)) {
            return ['reason' => 'unverified_other_risk'];
        }
        if ($peak['score'] > (int) $scope->risk_score || ($peak['score'] === (int) $scope->risk_score
            && ($matchingFormula || DB::table('risk_timeline')->where('ip', $scope->ip)->where('scope_id', $scope->id)
                ->where('event_type', self::RESCORE_EVENT)->where('risk_score', $scope->risk_score)->exists()))) {
            return ['reason' => 'score_needs_no_reduction'];
        }

        // Recompute the probe score even if an independent disposition must remain in effect.
        $decision = $scope->decision;
        if ($strongest !== 'pass' && $rank[$strongest] < ($rank[$decision] ?? 0)
            && ! $this->listed($scope) && ! app(RiskReviewEvidence::class)->locked((int) $scope->app_id, $scope->device_identity)) {
            $blockers = app(RiskReleaseEngine::class)->blockers($scope, true);
            if (! $scope->policy_eligible) {
                $blockers = array_filter($blockers, fn ($blocker) => $blocker['type'] !== 'review_status_unknown');
            }
            if ($blockers === []) {
                $decision = $strongest;
            }
        }

        return ['rescore' => $peak + ['decision' => $decision]];
    }

    private function sourceEvents(object $scope)
    {
        return DB::table('risk_timeline')->where('ip', $scope->ip)->where('scope_id', $scope->id)->whereIn('event_type', ['intake', 'reintake', 'risk_hit'])
            ->orderBy('id')->limit(5001)->get(['source_log_id', 'reason_codes', 'risk_score']);
    }

    private function rescore(object $scope, array $result): void
    {
        $log = $result['log'];
        $log->risk_score = $result['score'];
        $decision = $result['decision'];
        $level = DeviceEnvRiskView::level($result['score']);
        if (in_array($decision, ['limit', 'block'], true) && in_array($level, ['normal', 'watch'], true)) {
            $level = 'high';
        }
        DB::table('risk_scopes')->where('id', $scope->id)->update([
            'risk_score' => $result['score'], 'risk_level' => $level, 'decision' => $decision, 'updated_at' => now(),
        ]);
        $title = (int) $scope->risk_score === $result['score'] ? '补齐历史计分依据' : '暂停计分后重算：'.$scope->risk_score.' → '.$result['score'];
        // Attribute the score to the source event without making the device appear newly active.
        $this->ledger->timeline($scope->ip, self::RESCORE_EVENT, Carbon::parse($log->created_at)->timezone('Asia/Shanghai'), $log,
            $scope->device_identity, self::codes($scope->reason_codes), $decision, $level, (int) $scope->id, $title, null, 'system', '规则修正');
        $this->ledger->refreshDossier($scope->ip);
    }

    private function proof(?object $scope, bool $paused): array
    {
        $skip = fn ($reason) => ['reason' => $reason];
        if (! $scope || $scope->state !== 'active') {
            return $skip('not_active');
        }
        $allowed = $paused ? array_merge(DeviceEnvRiskService::PAUSED_SCORE_REASONS, ['native_probe_protocol_mismatch']) : self::ALLOWED;
        // Read raw persisted codes, never the display normalizer which hides retired signals.
        $codes = self::codes($scope->reason_codes);
        $evidenceCodes = DB::table('risk_reason_evidence')->where('scope_id', $scope->id)->pluck('code')->all();
        if (array_diff(array_merge($codes, $evidenceCodes), $allowed) || (! $paused && (int) $scope->risk_score > 70)) {
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
        $blockers = app(RiskReleaseEngine::class)->blockers($scope, true);
        if ($paused && ! $scope->policy_eligible) {
            // A statistics-only scope already has no effect on client policy. Keep every known hard block.
            $blockers = array_filter($blockers, fn ($blocker) => $blocker['type'] !== 'review_status_unknown');
        }
        if ($blockers !== []) {
            return $skip('release_blocker_or_unknown_review');
        }
        $events = $this->sourceEvents($scope);
        if ($events->isEmpty() || $events->count() > 5000 || ! $scope->source_log_id) {
            return $skip('incomplete_evidence');
        }
        $ids = [(int) $scope->source_log_id];
        foreach ($events as $event) {
            if (! $event->source_log_id || array_diff(self::codes($event->reason_codes), $allowed)) {
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
        $retired = $paused ? DeviceEnvRiskService::PAUSED_SCORE_REASONS : ['screen_projection'];
        $hasRetiredSignal = false;
        foreach ($logs as $log) {
            if ((int) $log->app_id !== (int) $scope->app_id || \App\Support\Services\DeviceEnvRiskView::identityFromLog($log) !== $scope->device_identity
                || \App\Services\Risk\Ledger\IpNormalizer::normalize($log->client_ip) !== $scope->ip || ($paused ? ! $this->pausedLogPasses($log, $allowed) : self::correctedDecision($log) === null)) {
                return $skip('source_has_other_or_unknown_risk');
            }
            $hasRetiredSignal = $hasRetiredSignal || array_intersect($retired, self::codes($log->risk_reasons)) !== [];
        }
        if (! $hasRetiredSignal) {
            return $skip('retired_signal_not_proven');
        }

        return ['reason' => null, 'source_ids' => $ids, 'source_count' => count($ids),
            'source_hash' => hash('sha256', $logs->toJson()), 'before' => [
                'risk_score' => (int) $scope->risk_score, 'risk_level' => $scope->risk_level, 'decision' => $scope->decision,
                'reason_codes' => self::codes($scope->reason_codes), 'basis_hash' => $scope->basis_hash, 'policy_eligible' => (bool) $scope->policy_eligible,
            ]];
    }

    private function pausedLogPasses(RiskProbeLog $log, array $allowed): bool
    {
        $probe = $log->probe_json;
        if ($log->status !== 'ok' || ! is_array($probe) || $probe === []
            || array_diff(self::codes($log->risk_reasons), $allowed)
            || array_diff(self::codes($probe['_risk_basis']['decision']['reasons'] ?? []), $allowed)) {
            return false;
        }

        $decision = app(DeviceEnvRiskService::class)->reassessProbe($probe, $log->validation_errors ?? []);

        return ! array_diff($decision['reasons'], $allowed)
            && \App\Support\Services\DeviceEnvRiskView::decision($decision['score'], $decision['compliance_mode'], $decision['ad_switch']) === 'pass';
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

    private function release(object $scope, array $proof, bool $paused): void
    {
        $now = Carbon::now('Asia/Shanghai');
        $rule = $paused ? self::PAUSED_RULE_ID : self::RULE_ID;
        $releaseId = DB::table('risk_releases')->insertGetId([
            'ip' => $scope->ip, 'released_at' => $now, 'status' => 'released', 'rule_id' => $rule, 'rule_version' => '1',
            'resource_version' => 1, 'idempotency_key' => $rule.':scope:'.$scope->id,
            'evidence' => json_encode($proof, JSON_UNESCAPED_UNICODE), 'explanation' => $paused ? '暂停计分后，关联原始证据不再满足风控名单收录条件。' : '投屏规则下线：核验全部关联原始证据，仅投屏导致的历史评分修正为 0，并解除本范围处置。',
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
            $paused ? '暂停计分后解除处置' : '投屏历史处置修正', $releaseId, 'system', '规则修正');
        $this->ledger->refreshDossier($scope->ip);
    }
}
