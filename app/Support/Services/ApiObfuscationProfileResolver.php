<?php

namespace App\Support\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\AppApiInterface;
use App\Models\AppApiObfuscationProfile;

class ApiObfuscationProfileResolver
{
    public function resolve(Request $request): array
    {
        $profiles = config('api_obfuscation.profiles', []);
        $default = $profiles['default'] ?? [];

        $appId = (string) $request->header('App-Id', '');
        $packageName = (string) $request->header('Package-Name', '');

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

    private function normalizeProfile(array $profile): array
    {
        $defaultProfile = config('api_obfuscation.profiles.default', []);
        $merged = array_replace_recursive($defaultProfile, $profile);

        return [
            'enabled' => (bool) (config('api_obfuscation.enabled', false) && ($merged['enabled'] ?? false)),
            'route_aliases' => $merged['route_aliases'] ?? [],
            'request_key_map' => $merged['request_key_map'] ?? [],
            'response_key_map' => $merged['response_key_map'] ?? [],
            'response_data_key_map' => $merged['response_data_key_map'] ?? [],
            'protocol' => $merged['protocol'] ?? [],
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

    private function buildRouteAliases(int $appId, string $packageName): array
    {
        $query = AppApiInterface::query()->where('is_enable', 1);
        if ($packageName !== '') {
            $query->where('package_name', $packageName);
        } else {
            $query->where('app_id', $appId);
        }

        $aliases = [];
        foreach ($query->get(['alias', 'path', 'method']) as $row) {
            if (empty($row['alias']) || empty($row['path'])) {
                continue;
            }
            $aliases[$row['alias']] = [
                'path' => ltrim((string) $row['path'], '/'),
                'method' => strtoupper((string) $row['method']),
            ];
        }

        return $aliases;
    }
}
