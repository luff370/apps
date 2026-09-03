<?php

namespace App\Support\Services;

use Throwable;
use Illuminate\Http\Request;
use App\Models\RiskProbeLog;

class RiskProbeAuditService
{
    /**
     * 行为指标既保留在完整 probe_json 中，也单独存入 behavior_json。
     * 单独拆分的目的不是再次评分，而是方便后台直接按触摸、点击、滑动、IMU 维度分析。
     */
    private const BEHAVIOR_FIELDS = [
        'touch_sample_count', 'touch_timing_entropy', 'touch_coord_dispersion',
        'touch_pressure_variation', 'touch_contact_variation', 'touch_pressure_zero_ratio',
        'click_sample_count', 'click_target_entropy', 'click_center_bias', 'click_in_bounds_ratio',
        'swipe_sample_count', 'swipe_linearity', 'swipe_speed_uniformity', 'swipe_micro_jitter',
        'sensor_static_score', 'gyro_static_score', 'imu_static_during_touch',
    ];

    public function record(Request $request, array $context): void
    {
        if (!config('api_obfuscation.device_env.audit_enabled', true)) {
            return;
        }

        // 没带 Device-Env 的请求（老版本、非客户端）没有探针可分析，不落库。
        if (($context['status'] ?? '') === 'missing') {
            return;
        }

        try {
            $probe = $context['probe'] ?? [];
            $validation = $context['validation'] ?? [];

            /*
             * 一次请求对应一条审计记录：
             * - status=ok：保存解密后的完整探针、行为子集、评分与决策；
             * - status=error：保存验签/解密/重放失败，便于排查异常客户端；
             * - nonce 不保存明文，只保存 SHA-256，避免数据库泄露后被直接用于重放。
             */
            RiskProbeLog::query()->create([
                'package_name' => $context['package_name'] ?: null,
                'app_id' => is_numeric($context['app_id'] ?? null) ? (int) $context['app_id'] : null,
                'route' => $request->path(),
                'request_method' => $request->method(),
                'status' => $context['status'] ?? 'error',
                'error' => $context['error'] ?? null,
                'nonce_hash' => isset($probe['nc']) ? hash('sha256', (string) $probe['nc']) : null,
                'probe_v' => $probe['probe_v'] ?? null,
                'env_schema_v' => $probe['env_schema_v'] ?? null,
                'platform' => $probe['platform'] ?? null,
                'risk_score' => $context['score'] ?? 0,
                'risk_reasons' => $context['reasons'] ?? [],
                'validation_errors' => $validation['errors'] ?? [],
                'env_digest_ok' => $validation['env_digest_ok'] ?? null,
                'env_field_count_ok' => $validation['env_field_count_ok'] ?? null,
                'env_allows_ads' => $probe['env_allows_ads'] ?? null,
                'compliance_mode' => $context['decision']['compliance_mode'] ?? 0,
                'ad_switch' => $context['decision']['ad_switch'] ?? 1,
                'probe_json' => $this->withoutReplaySecret($probe),
                'behavior_json' => $this->behavior($probe),
                'touch_sample_count' => $this->sampleCount($probe, 'touch_sample_count'),
                'click_sample_count' => $this->sampleCount($probe, 'click_sample_count'),
                'swipe_sample_count' => $this->sampleCount($probe, 'swipe_sample_count'),
                'client_ip' => $request->ip(),
                'app_version' => $request->header('App-Version'),
                'market_channel' => $request->header('Market-Channel'),
                'user_uuid' => $request->header('Uuid'),
                'device_sn' => $this->deviceSn($request),
            ]);
        } catch (Throwable $e) {
            // 风控审计不可用不能拖垮业务接口。
            report($e);
        }
    }

    private function withoutReplaySecret(array $probe): array
    {
        unset($probe['nc']);
        return $probe;
    }

    private function deviceSn(Request $request): ?string
    {
        // 大量 API 未登录，因此优先使用 Device-Sn 聚合；旧客户端没有该头时回退 Uuid。
        $deviceSn = trim((string) $request->header('Device-Sn', ''));
        if ($deviceSn === '') {
            $deviceSn = trim((string) $request->header('Uuid', ''));
        }

        return $deviceSn !== '' ? $deviceSn : null;
    }

    private function behavior(array $probe): array
    {
        return array_intersect_key($probe, array_flip(self::BEHAVIOR_FIELDS));
    }

    private function sampleCount(array $probe, string $field): ?int
    {
        // 样本数冗余成普通列，避免统计任务频繁扫描 JSON。
        return is_numeric($probe[$field] ?? null) ? max(0, (int) $probe[$field]) : null;
    }
}
