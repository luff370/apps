<?php

namespace App\Support\Services;

use App\Models\RiskProbeLog;
use App\Models\SystemApp;

/**
 * 把 Device-Env 审计记录映射成后台风控页使用的等级 / 决策结构。
 * 页面沿用 0–30 / 30–70 / 70–90 / 90–100；服务端实际关广告、开合规仍看 40 / 60 阈值。
 */
class DeviceEnvRiskView
{
    public const IDENTITY_SQL = "COALESCE(NULLIF(user_uuid, ''), NULLIF(device_sn, ''))";

    public static function identity(?string $uuid, ?string $deviceSn): string
    {
        $uuid = trim((string) $uuid);
        if ($uuid !== '') {
            return $uuid;
        }

        return trim((string) $deviceSn);
    }

    public static function identityFromLog(RiskProbeLog $log): string
    {
        return self::identity($log->user_uuid, $log->device_sn);
    }

    public static function level(int $score): string
    {
        if ($score >= 90) {
            return 'critical';
        }
        if ($score >= 70) {
            return 'high';
        }
        if ($score >= 30) {
            return 'watch';
        }

        return 'normal';
    }

    public static function levelRange(string $level): ?array
    {
        return match ($level) {
            'normal' => [0, 30],
            'watch' => [30, 70],
            'high' => [70, 90],
            'critical' => [90, 101],
            default => null,
        };
    }

    public static function decision(int $score, int $complianceMode, int $adSwitch): string
    {
        if ($complianceMode === 1) {
            return 'block';
        }
        if ($adSwitch === 0) {
            return 'limit';
        }
        if ($score >= 30) {
            return 'verify';
        }

        return 'pass';
    }

    public static function blockReason($reasons): string
    {
        if (is_string($reasons) && $reasons !== '') {
            return $reasons;
        }
        if (!is_array($reasons) || $reasons === []) {
            return '';
        }

        return implode('+', array_values(array_unique(array_filter($reasons))));
    }

    public static function eventType(RiskProbeLog $log): string
    {
        if ($log->env_allows_ads === false || (int) $log->compliance_mode === 1) {
            return 'guard';
        }

        return 'report';
    }

    public static function allowsAds(RiskProbeLog $log): int
    {
        if ((int) $log->ad_switch === 0 || $log->env_allows_ads === false) {
            return 0;
        }

        return 1;
    }

    public static function signalScore(array $reasons, array $keys, int $hitScore = 80): int
    {
        foreach ($keys as $key) {
            if (in_array($key, $reasons, true)) {
                return $hitScore;
            }
        }

        return 0;
    }

    public static function fingerprint(?string $seed): string
    {
        $seed = trim((string) $seed);
        if ($seed === '') {
            return '';
        }

        return substr(hash('sha256', $seed), 0, 16);
    }

    public static function formatTime($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_numeric($value) && (int) $value > 0) {
            return date('Y-m-d H:i:s', (int) $value);
        }
        $text = trim((string) $value);

        return $text === '' ? '' : $text;
    }

    public static function formatLog(RiskProbeLog $log, array $apps = [], array $channels = []): array
    {
        $score = (int) $log->risk_score;
        $reasons = is_array($log->risk_reasons) ? $log->risk_reasons : [];
        $probe = is_array($log->probe_json) ? $log->probe_json : [];
        $identity = self::identityFromLog($log);
        $appId = (int) $log->app_id;
        $channel = (string) $log->market_channel;

        return [
            'id' => (int) $log->id,
            'device_id' => (int) $log->id,
            'device_identity' => $identity,
            'app_id' => $appId,
            'app_name' => $apps[$appId] ?? '',
            'market_channel' => $channels[$channel] ?? $channel,
            'version' => (string) ($log->app_version ?? ''),
            'hardware_hash' => self::fingerprint($log->device_sn ?: $identity),
            'sensor_hash' => self::fingerprint((string) ($probe['sensor_static_score'] ?? '') . '|' . $identity),
            'install_id' => (string) ($log->device_sn ?: $identity),
            'risk_score' => $score,
            'risk_level' => self::level($score),
            'decision' => self::decision($score, (int) $log->compliance_mode, (int) $log->ad_switch),
            'root_score' => self::signalScore($reasons, ['root', 'jailbroken']),
            'hook_score' => self::signalScore($reasons, ['hook']),
            'emulator_score' => self::signalScore($reasons, ['emulator', 'emulator_fingerprint', 'cloud_phone', 'monkey']),
            'allows_ads' => self::allowsAds($log),
            'probe_v' => $log->probe_v,
            'block_reason' => self::blockReason($reasons),
            'network_type' => (string) ($probe['network_transport'] ?? ''),
            'event_type' => self::eventType($log),
            'user_id' => 0,
            'created_at' => self::formatTime($log->created_at),
            'last_report_at' => self::formatTime($log->created_at),
        ];
    }

    public static function appNameMap(): array
    {
        return SystemApp::idToNameMap();
    }

    public static function channelMap(): array
    {
        return SystemApp::marketChannelsMap();
    }
}
