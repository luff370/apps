<?php

namespace App\Services\Risk\Ledger;

class ReleaseRule
{
    public const ID = 'continuous-use-release';

    public const VERSION = '4';

    public const THRESHOLD_SECONDS = 1800;

    public const AUTO_SAFE_REASONS = ['vpn', 'sensor_static', 'native_probe_protocol_mismatch', 'device_env_digest_mismatch', 'env_field_count_mismatch'];

    /** 以下三项是联调默认值，尚未作为已确认产品事实。缺字段时不自动释放。 */
    public const IDLE_STOP_SECONDS = 300;

    public const GAP_SECONDS = 90;

    public const BACKGROUND_SPLIT_SECONDS = 300;

    public const ONLINE_SKEW_SECONDS = 120;

    public static function describe(int $seconds): string
    {
        $minutes = (int) floor($seconds / 60);

        return "本次有效前台使用 {$minutes} 分钟，进入低风险观察后有新的有效在线操作，且完整风险依据符合自动释放条件。对应应用和设备进入安全区。";
    }

    public static function payload(): array
    {
        return [
            'id' => self::ID,
            'version' => self::VERSION,
            'confirmed' => [
                'threshold_seconds' => self::THRESHOLD_SECONDS,
                'same_app_same_device' => true,
                'background_and_lock_screen_excluded' => true,
                'requires_interaction_after_threshold' => true,
                'target_stage' => 'safe',
                'requires_observation_before_safe' => true,
                'requires_interaction_after_observation' => true,
                'auto_safe_reasons' => self::AUTO_SAFE_REASONS,
            ],
            'provisional' => [
                'idle_stop_seconds' => self::IDLE_STOP_SECONDS,
                'gap_seconds' => self::GAP_SECONDS,
                'background_split_seconds' => self::BACKGROUND_SPLIT_SECONDS,
                'foreground_report_seconds' => 30,
                'online_skew_seconds' => self::ONLINE_SKEW_SECONDS,
            ],
            'hard_blocks' => ['reviewing_visit', 'test_account', 'prevent_auto_release'],
        ];
    }
}
