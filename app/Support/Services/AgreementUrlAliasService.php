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
    /** 算出该应用某类协议的 8 位路径别名。 */
    public function make(int $appId, string $packageName, string $type): string
    {
        return $this->stableUrlAlias($this->identity($appId, $packageName, $type));
    }

    /** 拼出可访问的协议 URL：{api_domain}/{alias}/{platform}。 */
    public function url(int $appId, string $packageName, string $type, string $platform, ?string $root = null): string
    {
        $path = $this->make($appId, $packageName, $type) . '/' . trim($platform, '/');

        if ($root !== null && $root !== '') {
            return rtrim($root, '/') . '/' . $path;
        }

        return url($path);
    }

    /** 用别名反查是哪个应用的哪类协议；用于协议页路由。 */
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

    /** 与接口 URL 别名同一套 identity 形态，path 固定为 agreement/{type}。 */
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
