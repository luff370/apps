<?php

namespace App\Support\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\AppApiObfuscationAlias;
use App\Models\AppApiObfuscationProfile;

class ApiObfuscationProfileResolver
{
    /**
     * 按请求头里的应用身份解析混淆配置：先查库，再回退到 config 里按 app_id / 包名 / default。
     */
    public function resolve(Request $request): array
    {
        $profiles = config('api_obfuscation.profiles', []);
        $default = $profiles['default'] ?? [];

        $appId = (string) (ClientRequestContext::appId($request) ?? '');
        $packageName = (string) (ClientRequestContext::packageName($request) ?? '');

        $dbProfile = $this->resolveFromDatabase($appId, $packageName);
        if (!empty($dbProfile)) {
            return $this->normalizeProfile($dbProfile);
        }

        if ($appId !== '' && isset($profiles[$appId])) {
            return $this->normalizeProfile($profiles[$appId]);
        }

        if ($packageName !== '' && isset($profiles[$packageName])) {
            return $this->normalizeProfile($profiles[$packageName]);
        }

        return $this->normalizeProfile($default);
    }

    /** 与默认配置合并，并套上全局 enabled 开关。 */
    private function normalizeProfile(array $profile): array
    {
        $defaultProfile = config('api_obfuscation.profiles.default', []);
        $merged = array_replace_recursive($defaultProfile, $profile);

        $protocol = (array) ($merged['protocol'] ?? []);
        if (array_key_exists('encrypt_request', $merged)) {
            $protocol['encrypt_request'] = (bool) $merged['encrypt_request'];
        }
        if (array_key_exists('encrypt_response', $merged)) {
            $protocol['encrypt_response'] = (bool) $merged['encrypt_response'];
        }

        return [
            'enabled' => (bool) (config('api_obfuscation.enabled', false) && ($merged['enabled'] ?? false)),
            'app_id' => $merged['app_id'] ?? null,
            'package_name' => $merged['package_name'] ?? null,
            'route_aliases' => $merged['route_aliases'] ?? [],
            'request_key_map' => $this->defaultKeyMap($merged['request_key_map'] ?? [], 'request_key_map'),
            'response_key_map' => $this->defaultKeyMap($merged['response_key_map'] ?? [], 'response_key_map'),
            'response_data_key_map' => $merged['response_data_key_map'] ?? [],
            'protocol' => $protocol,
            'security' => $merged['security'] ?? [],
            'crypto' => $merged['crypto'] ?? [],
            'image_url' => $merged['image_url'] ?? [],
        ];
    }

    private function resolveFromDatabase(string $appId, string $packageName): array
    {
        if ($appId === '' && $packageName === '') {
            return [];
        }

        $cacheKey = sprintf('api_obf_profile:%s:%s', $appId, $packageName);
        return Cache::remember($cacheKey, 60, function () use ($appId, $packageName) {
            $query = AppApiObfuscationProfile::query();
            if ($packageName !== '') {
                $query->where('package_name', $packageName);
            } else {
                $query->where('app_id', intval($appId));
            }

            $row = $query->first();
            if (!$row) {
                return [];
            }

            $profile = $row->toArray();
            $profile['route_aliases'] = $this->buildRouteAliases(intval($row['app_id']), (string) $row['package_name']);
            return $profile;
        });
    }

    /**
     * 运行时路由别名：启用中的别名 + 仍存在的公共 API。
     * 公共接口已删的行会被跳过，不会进网关转发表。
     */
    private function buildRouteAliases(int $appId, string $packageName): array
    {
        $profileQuery = AppApiObfuscationProfile::query();
        if ($packageName !== '') {
            $profileQuery->where('package_name', $packageName);
        } else {
            $profileQuery->where('app_id', $appId);
        }

        $profile = $profileQuery->first();
        if (!$profile) {
            return [];
        }

        $aliases = [];
        $rows = AppApiObfuscationAlias::query()
            ->where('profile_id', $profile['id'])
            ->where('is_enable', 1)
            ->with('apiInterface')
            ->get();

        foreach ($rows as $row) {
            if (!$row->apiInterface || empty($row['alias']) || empty($row->apiInterface['path'])) {
                continue;
            }
            $responseKeyMap = (array) ($row['response_key_map'] ?? []);
            if (empty($responseKeyMap)) {
                $responseKeyMap = (array) ($row['response_data_key_map'] ?? []);
            }
            $aliases[$row['alias']] = [
                'path' => ltrim((string) $row->apiInterface['path'], '/'),
                'method' => strtoupper((string) $row->apiInterface['method']),
                'request_key_map' => (array) ($row['request_key_map'] ?? []),
                'response_key_map' => $responseKeyMap,
                'response_data_key_map' => $responseKeyMap,
            ];
        }

        return $aliases;
    }

    private function defaultKeyMap(mixed $map, string $configKey): array
    {
        $map = is_array($map) ? $map : [];

        return $map !== [] ? $map : (array) config('api_obfuscation.default_short_maps.' . $configKey, []);
    }
}
