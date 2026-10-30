<?php

namespace App\Services\Risk;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RiskPolicyService
{
    public static function basis(array $decision): string
    {
        $reasons = $decision['reasons'] ?? [];
        sort($reasons);

        return hash('sha256', json_encode([(int) ($decision['score'] ?? 0), $reasons,
            (int) ($decision['compliance_mode'] ?? 0), (int) ($decision['ad_switch'] ?? 1)]));
    }

    public function overlay(array $decision, int $appId, string $device, array $probe): array
    {
        if ($appId <= 0 || $device === '') {
            return $decision;
        }
        $query = DB::table('risk_scopes')->where('app_id', $appId)->where('device_identity', $device)->where('policy_eligible', true);
        $active = (clone $query)->where('state', 'active')->selectRaw("MAX(risk_score) as score, MAX(CASE WHEN decision = 'block' THEN 1 ELSE 0 END) as blocks, MAX(CASE WHEN decision = 'limit' THEN 1 ELSE 0 END) as limits")->first();
        if ($active->score !== null) {
            $decision['score'] = max((int) $decision['score'], (int) $active->score);
            if ($active->blocks) {
                $decision['compliance_mode'] = 1;
                $decision['ad_switch'] = 0;
            } elseif ($active->limits) {
                $decision['ad_switch'] = 0;
            }

            return $decision;
        }
        $released = (clone $query)->where('state', 'released')->where('basis_hash', self::basis($decision))->orderByDesc('id')->first();
        if (! $released) {
            return $decision;
        }
        $release = DB::table('risk_releases')->where('id', $released->release_id)->where('status', 'released')->first();
        if (! $release) {
            return $decision;
        }
        $review = app(RiskReviewEvidence::class);
        $reviewState = $review->statusAt($appId, $device, (string) ($probe['app_version'] ?? ''), (string) ($probe['market_channel'] ?? ''), Carbon::now('Asia/Shanghai'));
        if (in_array($reviewState, ['blocked', 'unknown'], true) || $review->locked($appId, $device) || app(RiskReleaseEngine::class)->blockers($released, true) !== []) {
            return $decision;
        }

        return array_replace($decision, ['score' => 0, 'reasons' => [], 'compliance_mode' => 0, 'ad_switch' => 1, 'released_scope_id' => (string) $released->id]);
    }
}
