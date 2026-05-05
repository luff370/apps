<?php

namespace App\Services\App;

use App\Dao\App\AppApiInterfaceDao;
use App\Services\Service;
use Illuminate\Support\Str;
use App\Dao\App\AppApiObfuscationProfileDao;

class AppApiObfuscationService extends Service
{
    public function __construct(
        AppApiObfuscationProfileDao $dao,
        private AppApiInterfaceDao $interfaceDao
    ) {
        $this->dao = $dao;
    }

    public function getProfile(int $appId = 0, string $packageName = ''): array
    {
        $profile = $this->dao->search(['app_id' => $appId, 'package_name' => $packageName])->first();
        if (empty($profile)) {
            $default = config('api_obfuscation.profiles.default', []);
            return array_merge([
                'id' => 0,
                'app_id' => $appId,
                'package_name' => $packageName,
                'alias_rule' => 'hash4',
            ], $default);
        }

        $result = $profile->toArray();
        $result['route_aliases'] = $this->buildRouteAliases($appId, $packageName);
        return $result;
    }

    public function saveProfile(array $data): array
    {
        $where = [
            'app_id' => intval($data['app_id'] ?? 0),
            'package_name' => (string) ($data['package_name'] ?? ''),
        ];
        $profile = $this->dao->search($where)->first();

        $protocol = [
            'encrypt_request' => (bool) ($data['encrypt_request'] ?? 0),
            'encrypt_response' => (bool) ($data['encrypt_response'] ?? 0),
            'allow_plaintext_request' => (bool) ($data['allow_plaintext_request'] ?? 1),
            'payload_field' => (string) ($data['payload_field'] ?? 'payload'),
            'sign_field' => (string) ($data['sign_field'] ?? 'sign'),
            'timestamp_field' => (string) ($data['timestamp_field'] ?? 'ts'),
            'nonce_field' => (string) ($data['nonce_field'] ?? 'nonce'),
            'version_field' => (string) ($data['version_field'] ?? 'ver'),
        ];

        $imageUrl = [
            'enabled' => (bool) ($data['image_url_enabled'] ?? 0),
            'domain' => (string) ($data['image_domain'] ?? ''),
            'fields' => $this->splitLinesToArray((string) ($data['image_fields'] ?? '')),
            'path_prefixes' => $this->splitLinesToArray((string) ($data['image_prefixes'] ?? '')),
        ];

        $security = [
            'timestamp_window_seconds' => intval($data['timestamp_window_seconds'] ?? 300),
            'nonce_ttl_seconds' => intval($data['nonce_ttl_seconds'] ?? 300),
        ];

        $crypto = [
            'cipher' => (string) ($data['cipher'] ?? 'AES-256-CBC'),
            'key' => (string) ($data['crypto_key'] ?? ''),
            'iv' => (string) ($data['crypto_iv'] ?? ''),
            'sign_key' => (string) ($data['crypto_sign_key'] ?? ''),
        ];

        $save = [
            'enabled' => intval($data['enabled'] ?? 0),
            'encrypt_request' => intval($data['encrypt_request'] ?? 0),
            'encrypt_response' => intval($data['encrypt_response'] ?? 0),
            'allow_plaintext_request' => intval($data['allow_plaintext_request'] ?? 1),
            'image_url_enabled' => intval($data['image_url_enabled'] ?? 0),
            'image_domain' => (string) ($data['image_domain'] ?? ''),
            'alias_rule' => (string) ($data['alias_rule'] ?? 'hash4'),
            'request_key_map' => $this->decodeJson((string) ($data['request_key_map'] ?? '{}')),
            'response_key_map' => $this->decodeJson((string) ($data['response_key_map'] ?? '{}')),
            'response_data_key_map' => $this->decodeJson((string) ($data['response_data_key_map'] ?? '{}')),
            'protocol' => $protocol,
            'security' => $security,
            'crypto' => $crypto,
            'image_url' => $imageUrl,
            'route_aliases' => $this->buildRouteAliases($where['app_id'], $where['package_name']),
        ];

        if ($profile) {
            $this->dao->update($profile['id'], $save);
        } else {
            $save = array_merge($where, $save);
            $profile = $this->dao->save($save);
        }

        return $this->getProfile($where['app_id'], $where['package_name']);
    }

    public function generateAliases(array $data): array
    {
        $appId = intval($data['app_id'] ?? 0);
        $packageName = (string) ($data['package_name'] ?? '');
        $rule = (string) ($data['rule'] ?? 'hash4');
        $overwrite = intval($data['overwrite'] ?? 0) === 1;

        $rows = $this->interfaceDao->search(['app_id' => $appId, 'package_name' => $packageName])->get();
        $used = [];
        foreach ($rows as $row) {
            if (!empty($row['alias'])) {
                $used[$row['alias']] = true;
            }
        }

        $updated = 0;
        foreach ($rows as $row) {
            if (!$overwrite && !empty($row['alias'])) {
                continue;
            }

            $seed = strtoupper($row['method']) . ':' . trim($row['path'], '/');
            $alias = $this->makeAlias($seed, $rule, $used);
            $this->interfaceDao->update($row['id'], ['alias' => $alias]);
            $updated++;
        }

        $this->saveProfile([
            'app_id' => $appId,
            'package_name' => $packageName,
            'alias_rule' => $rule,
        ]);

        return ['updated' => $updated, 'rule' => $rule];
    }

    public function buildRouteAliases(int $appId, string $packageName): array
    {
        $list = $this->interfaceDao->search([
            'app_id' => $appId,
            'package_name' => $packageName,
            'is_enable' => 1
        ])->get();

        $aliases = [];
        foreach ($list as $item) {
            if (empty($item['alias']) || empty($item['path'])) {
                continue;
            }
            $aliases[$item['alias']] = [
                'path' => ltrim($item['path'], '/'),
                'method' => strtoupper((string) $item['method']),
            ];
        }

        return $aliases;
    }

    private function decodeJson(string $json): array
    {
        $decoded = json_decode(trim($json), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function splitLinesToArray(string $text): array
    {
        $parts = preg_split('/\r\n|\r|\n/', $text);
        return array_values(array_filter(array_map('trim', $parts ?: []), fn($v) => $v !== ''));
    }

    private function makeAlias(string $seed, string $rule, array &$used): string
    {
        $try = 0;
        do {
            $try++;
            $alias = match ($rule) {
                'hex6' => substr(md5($seed . ':' . $try), 0, 6),
                'mix6' => $this->alphaNumFromHash($seed . ':' . $try, 6),
                'word4' => $this->wordAlias($seed, $try),
                default => substr(md5($seed . ':' . $try), 0, 4),
            };
        } while (isset($used[$alias]) && $try < 20);

        $used[$alias] = true;
        return $alias;
    }

    private function alphaNumFromHash(string $seed, int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $hex = md5($seed);
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $n = hexdec($hex[$i]) % strlen($chars);
            $result .= $chars[$n];
        }
        return $result;
    }

    private function wordAlias(string $seed, int $salt): string
    {
        $prefix = ['ax', 'ke', 'zu', 'vo', 'mi', 'ra', 'ta', 'ny'];
        $suffix = ['q', 'x', 'k', 'm', 'z', 't', 'v', 's'];
        $hash = crc32($seed . ':' . $salt);
        $p = $prefix[$hash % count($prefix)];
        $s = $suffix[($hash >> 3) % count($suffix)];
        return $p . substr(md5($seed), 0, 2) . $s;
    }
}

