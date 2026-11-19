<?php

namespace App\Services\Risk;

use App\Exceptions\AdminException;
use App\Services\Risk\Ledger\ReasonCatalog;
use App\Services\Risk\Ledger\ReleaseRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Explicit dispositions are scoped to App + device and the reviewed set of reasons. */
class RiskReviewWorkflow
{
    public function canReleaseAutomatically(object $scope): bool
    {
        $signals = array_diff($this->baseline($scope), ['env_blocks_ads']);

        return $scope->reviewed_at !== null && $signals !== []
            && array_diff($signals, ReleaseRule::AUTO_SAFE_REASONS) === [];
    }

    public function matching(int $appId, string $device, array $decision): ?object
    {
        $scopes = DB::table('risk_scopes')->where('app_id', $appId)->where('device_identity', $device)
            ->whereIn('review_stage', ['observation', 'safe'])->orderByDesc('reviewed_at')->orderByDesc('id')->get();
        $reasons = ReasonCatalog::normalizeList($decision['reasons'] ?? []);
        if ($reasons === [] && (int) ($decision['score'] ?? 0) > 0) {
            return null;
        }
        if ($reasons === ['env_blocks_ads']) {
            return null;
        }
        $reasons = array_values(array_diff($reasons, ['env_blocks_ads']));
        foreach ($scopes as $scope) {
            $baseline = json_decode((string) $scope->review_reasons, true);
            // Never grant a blanket exemption for records without a precise reviewed baseline.
            if (! is_array($baseline) || $baseline === [] || array_diff($reasons, $baseline)) {
                continue;
            }
            if (($scope->review_stage === 'safe' && $scope->state !== 'released') || ($scope->review_stage === 'observation' && $scope->state !== 'active')) {
                continue;
            }
            if (DB::table('risk_scopes')->where('app_id', $appId)->where('device_identity', $device)->where('state', 'active')->where('policy_eligible', true)->where('review_stage', 'enforced')->exists()) {
                return null;
            }

            return $scope;
        }

        return null;
    }

    public function decision(array $decision, object $scope): array
    {
        return array_replace($decision, [
            'score' => $scope->review_stage === 'safe' ? 0 : min(39, (int) $decision['score']),
            'reasons' => $scope->review_stage === 'safe' ? [] : $decision['reasons'],
            'compliance_mode' => 0, 'ad_switch' => 1,
        ]);
    }

    public function prior(string $requestId, string $ip): ?array
    {
        if ($requestId === '' || strlen($requestId) > 64) {
            throw new AdminException('缺少有效的处置请求号，请刷新后重试');
        }
        $row = DB::table('risk_review_actions')->where('request_id', $requestId)->first();
        if (! $row) {
            return null;
        }
        if ($row->ip !== $ip) {
            throw new AdminException('请求号已用于另一条处置', 409);
        }

        return json_decode($row->result, true);
    }

    public function validate(object $dossier, array $transition): void
    {
        if (($transition['expected_state'] ?? '') !== $dossier->state || (int) ($transition['expected_version'] ?? 0) !== (int) $dossier->resource_version) {
            throw new AdminException('风控状态已变化，请刷新后重新确认', 409);
        }
    }

    public function downgrade($scopes, string $ip, int $operatorId, string $operatorName, string $requestId): array
    {
        $from = (string) DB::table('risk_ip_dossiers')->where('ip', $ip)->value('state');
        $before = $scopes->map(fn ($s) => (array) $s)->all();
        $now = Carbon::now('Asia/Shanghai');
        foreach ($scopes as $scope) {
            $baseline = $this->baseline($scope);
            DB::table('risk_scopes')->where('id', $scope->id)->update([
                'review_stage' => 'observation', 'review_reasons' => json_encode($baseline), 'reviewed_at' => $now,
                'risk_score' => 30, 'risk_level' => 'watch', 'decision' => 'verify', 'updated_at' => $now,
            ]);
            app(RiskLedgerService::class)->scopeEvent((int) $scope->id, $ip, 'risk_downgrade', $now, true, null);
        }
        $first = $scopes->first();
        app(RiskLedgerService::class)->timeline($ip, 'risk_downgrade', $now, null, $first->device_identity, [], 'verify', 'watch',
            (int) $first->id, '降为低风险观察', null, $operatorId ? 'admin' : 'system', $operatorName);
        app(RiskLedgerService::class)->refreshDossier($ip);
        $result = ['changed' => true, 'state' => (string) DB::table('risk_ip_dossiers')->where('ip', $ip)->value('state'), 'release_id' => null, 'scope_count' => $scopes->count(), 'downgraded' => true];
        $result = $this->record($ip, $operatorId, $operatorName, $requestId, $from, $result['state'], $before, $result);

        return $result;
    }

    public function baseline(object $scope): array
    {
        $old = json_decode((string) ($scope->review_reasons ?? ''), true) ?: [];

        return array_values(array_unique(array_merge($old, ReasonCatalog::normalizeList(json_decode((string) $scope->reason_codes, true) ?: []))));
    }

    public function record(string $ip, int $operatorId, string $operatorName, string $requestId, string $from, string $to, array $before, array $result): array
    {
        if ($from === $to) {
            DB::table('risk_ip_dossiers')->where('ip', $ip)->increment('resource_version');
        }
        $result['resource_version'] = (int) DB::table('risk_ip_dossiers')->where('ip', $ip)->value('resource_version');
        DB::table('risk_review_actions')->insert([
            'request_id' => $requestId, 'ip' => $ip, 'from_state' => $from, 'to_state' => $to,
            'operator_id' => $operatorId, 'operator_name' => $operatorName, 'before_scopes' => json_encode($before),
            'result' => json_encode($result), 'created_at' => now(),
        ]);

        return $result;
    }
}
