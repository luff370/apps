<?php

namespace App\Support\Services;

use Illuminate\Http\Request;

class ApiObfuscationProfileResolver
{
    public function resolve(Request $request): array
    {
        $profiles = config('api_obfuscation.profiles', []);
        $default = $profiles['default'] ?? [];

        $appId = (string) $request->header('App-Id', '');
        $packageName = (string) $request->header('Package-Name', '');

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
        return [
            'enabled' => (bool) ($profile['enabled'] ?? false),
            'route_aliases' => $profile['route_aliases'] ?? [],
            'request_key_map' => $profile['request_key_map'] ?? [],
            'response_key_map' => $profile['response_key_map'] ?? [],
            'response_data_key_map' => $profile['response_data_key_map'] ?? [],
        ];
    }
}
