<?php

namespace App\Services\Risk\Ledger;

class ReleaseRule
{
    public const ID = 'continuous-use-release';

    public const VERSION = '1';

    public const THRESHOLD_SECONDS = 1800;

    /** 以下三项是联调默认值，尚未作为已确认产品事实。缺字段时不自动释放。 */
    public const IDLE_STOP_SECONDS = 300;

    public const GAP_SECONDS = 90;

    public const BACKGROUND_SPLIT_SECONDS = 300;

    public const ONLINE_SKEW_SECONDS = 120;

    public static function describe(int $seconds): string
    {
        $minutes = (int) floor($seconds / 60);

        return "本次有效前台使用 {$minutes} 分钟，达到 30 分钟阈值后仍有操作，且没有有效的禁止自动释放条件。只解除本次对应的应用和设备。";
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
