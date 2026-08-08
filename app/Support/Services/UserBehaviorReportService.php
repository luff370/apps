<?php

namespace App\Support\Services;

use App\Models\UserBehaviorReport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserBehaviorReportService
{
    private const BEHAVIOR_FIELDS = [
        'touch_sample_count', 'touch_timing_entropy', 'touch_coord_dispersion',
        'touch_pressure_variation', 'touch_contact_variation', 'touch_pressure_zero_ratio',
        'click_sample_count', 'click_target_entropy', 'click_center_bias', 'click_in_bounds_ratio',
        'swipe_sample_count', 'swipe_linearity', 'swipe_speed_uniformity', 'swipe_micro_jitter',
        'sensor_static_score', 'gyro_static_score', 'imu_static_during_touch',
    ];

    public function record(Request $request): UserBehaviorReport
    {
        $payload = $request->validate([
            'behavior' => ['sometimes', 'array'],
            'device_environment' => ['sometimes', 'array'],
            'ad_extension' => ['sometimes', 'array'],
            'reported_at' => ['sometimes', 'integer', 'min:1'],
        ]);
        $behaviorSource = is_array($payload['behavior'] ?? null) ? $payload['behavior'] : $request->all();
        $behavior = array_intersect_key($behaviorSource, array_flip(self::BEHAVIOR_FIELDS));
        $this->validateBehavior($behavior);

        if (!$this->hasSamples($behavior)) {
            throw ValidationException::withMessages([
                'behavior' => ['至少需要一项大于 0 的 touch/click/swipe 样本数'],
            ]);
        }

        $deviceEnvironment = $payload['device_environment'] ?? [];
        $adExtension = $payload['ad_extension'] ?? [];
        $appId = $request->header('App-Id', $request->input('app_id', 0));

        return UserBehaviorReport::query()->create([
            'user_id' => (int) $request->authUserId(),
            'app_id' => is_numeric($appId) ? (int) $appId : 0,
            'uuid' => (string) $request->header('Uuid', ''),
            'device_sn' => (string) $request->header('Device-Sn', $request->header('Uuid', '')),
            'package_name' => (string) $request->header('Package-Name', ''),
            'platform' => (string) $request->header('Platform', ''),
            'app_version' => (string) $request->header('App-Version', ''),
            'market_channel' => (string) $request->header('Market-Channel', ''),
            'ip' => (string) $request->ip(),
            'behavior' => $behavior,
            'device_environment' => $deviceEnvironment,
            'ad_extension' => $adExtension,
            'client_reported_at' => isset($payload['reported_at'])
                ? date('Y-m-d H:i:s', (int) $payload['reported_at'])
                : null,
        ]);
    }

    private function validateBehavior(array $behavior): void
    {
        $errors = [];
        foreach ($behavior as $field => $value) {
            if (!is_int($value) && !is_float($value)) {
                $errors["behavior.{$field}"][] = '必须是数值';
                continue;
            }

            $max = str_ends_with($field, '_count') ? 1000000 : 100;
            if ($value < 0 || $value > $max) {
                $errors["behavior.{$field}"][] = "必须在 0 到 {$max} 之间";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function hasSamples(array $behavior): bool
    {
        foreach (['touch_sample_count', 'click_sample_count', 'swipe_sample_count'] as $field) {
            if ((int) ($behavior[$field] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

}
