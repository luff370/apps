<?php

namespace App\Support\Services;

use App\Models\SystemApp;
use App\Models\AppAgreement;

/**
 * 协议页 URL 别名：算法与接口别名 stableUrlAlias 一致，
 * 由 应用ID + 包名 + 协议类型 现场算出 8 位稳定值，生成路径不读不写缓存。
 */
class AgreementUrlAliasService
{
    public function make(int $appId, string $packageName, string $type): string
    {
        return $this->stableUrlAlias($this->identity($appId, $packageName, $type));
    }

    public function url(int $appId, string $packageName, string $type, string $platform, ?string $root = null): string
    {
        $path = $this->make($appId, $packageName, $type) . '/' . trim($platform, '/');

        if ($root !== null && $root !== '') {
            return rtrim($root, '/') . '/' . $path;
        }

        return url($path);
    }

    public function resolve(string $alias): ?array
    {
        $apps = SystemApp::query()->where('is_del', 0)->get(['id', 'package_name']);
        foreach ($apps as $app) {
            foreach (array_keys(AppAgreement::typesMap()) as $type) {
                if ($this->make((int) $app['id'], (string) $app['package_name'], $type) === $alias) {
                    return [
                        'app_id' => (int) $app['id'],
                        'type' => $type,
                    ];
                }
            }
        }

        return null;
    }

    private function identity(int $appId, string $packageName, string $type): string
    {
        $type = trim($type, '/');

        return $appId . '|' . trim($packageName) . '|GET|agreement/' . $type;
    }

    private function stableUrlAlias(string $identity, int $salt = 0): string
    {
        $key = 'api_alias|' . $identity;
        $hash = hash_hmac('sha256', 'url' . ($salt > 0 ? '|' . $salt : ''), $key, true);
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $alias = '';
        for ($i = 0; $i < 8; $i++) {
            $alias .= $chars[ord($hash[$i]) % 36];
        }

        return $alias;
    }
}
