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
}
