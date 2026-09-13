<?php

namespace App\Support\Services;

use Illuminate\Http\Request;

/**
 * 客户端身份字段：Header 优先，没有再读已解密的 Device-Env。
 * App-Id 不进 Device-Env，老客户端仍带头，新客户端用包名映射。
 */
class ClientRequestContext
{
    public static function appId(?Request $request = null): ?string
    {
        $request = self::request($request);
        $header = self::trimmedHeader($request, 'App-Id');
        if ($header !== null) {
            return $header;
        }

        $id = AppPackageMap::idByPackage(self::packageName($request));

        return $id ? (string) $id : null;
    }

    public static function uuid(?Request $request = null): ?string
    {
        return self::headerThenProbe('Uuid', 'uuid', $request);
    }

    public static function appVersion(?Request $request = null): ?string
    {
        return self::headerThenProbe('App-Version', 'app_version', $request);
    }

    public static function osVersion(?Request $request = null): ?string
    {
        return self::headerThenProbe('OS-Version', 'os_version', $request);
    }

    public static function marketChannel(?Request $request = null): ?string
    {
        $value = self::headerThenProbe('Market-Channel', 'market_channel', $request);

        return $value === null ? null : strtolower($value);
    }

    public static function deviceSn(?Request $request = null): ?string
    {
        $device = self::headerThenProbe('Device-Sn', 'device_sn', $request);
        if ($device !== null) {
            return $device;
        }

        return self::uuid($request);
    }

    public static function packageName(?Request $request = null): ?string
    {
        return self::trimmedHeader(self::request($request), 'Package-Name');
    }

    public static function platform(?Request $request = null): ?string
    {
        $value = self::headerThenProbe('Platform', 'platform', $request);

        return $value === null ? null : strtolower($value);
    }

    public static function token(?Request $request = null): ?string
    {
        return self::headerThenProbe('Token', 'token', $request);
    }

    public static function headerThenProbe(string $header, string $probeKey, ?Request $request = null): ?string
    {
        $request = self::request($request);
        $value = self::trimmedHeader($request, $header);
        if ($value !== null) {
            return $value;
        }

        $probe = $request->attributes->get('device_env_risk')['probe'] ?? [];
        if (!array_key_exists($probeKey, $probe) || $probe[$probeKey] === null || $probe[$probeKey] === '') {
            return null;
        }

        return trim((string) $probe[$probeKey]);
    }

    private static function trimmedHeader(Request $request, string $header): ?string
    {
        $value = trim((string) $request->header($header, ''));

        return $value === '' ? null : $value;
    }

    private static function request(?Request $request): Request
    {
        return $request ?: request();
    }
}
