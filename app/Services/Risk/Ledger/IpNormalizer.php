<?php

namespace App\Services\Risk\Ledger;

class IpNormalizer
{
    public static function normalize(?string $value): string
    {
        $ip = trim((string) $value);
        if ($ip === '' || str_contains($ip, ',') || str_contains($ip, '/') || str_contains($ip, ' ')) {
            return '';
        }
        if (str_starts_with($ip, '[') && str_ends_with($ip, ']')) {
            $ip = substr($ip, 1, -1);
        }
        if (preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $ip)) {
            $parts = array_map(static fn ($part) => (int) $part, explode('.', $ip));
            if (max($parts) > 255) {
                return '';
            }

            return implode('.', $parts);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            return $packed === false ? '' : (inet_ntop($packed) ?: '');
        }

        return '';
    }

    public static function version(string $ip): int
    {
        return str_contains($ip, ':') ? 6 : 4;
    }

    public static function idFor(string $ip): string
    {
        return rtrim(strtr(base64_encode($ip), '+/', '-_'), '=');
    }

    public static function ipFor(string $id): string
    {
        $remainder = strlen($id) % 4;
        if ($remainder) {
            $id .= str_repeat('=', 4 - $remainder);
        }
        $raw = base64_decode(strtr($id, '-_', '+/'), true);

        return is_string($raw) ? self::normalize($raw) : '';
    }
}
