<?php

namespace App\Support\Services;

/**
 * Device-Env 请求头别名。
 * 由 应用ID + 包名 现场算出 8 位稳定值，不落库。
 * 客户端用该别名传探针密文，服务端仍接受明文 Device-Env。
 */
class DeviceEnvHeaderAliasService
{
    public const ORIGIN_HEADER = 'Device-Env';

    /** 该应用客户端应使用的 Device-Env 请求头名。 */
    public function make(int $appId, string $packageName): string
    {
        return $this->stableHeaderAlias($this->identity($appId, $packageName));
    }

    private function identity(int $appId, string $packageName): string
    {
        return $appId . '|' . trim($packageName) . '|header|' . self::ORIGIN_HEADER;
    }

    private function stableHeaderAlias(string $identity): string
    {
        // HMAC 的 key 绑定应用身份，message 固定为 header，和 URL 别名的 message=url 区分开。
        $hash = hash_hmac('sha256', 'header', 'api_alias|' . $identity, true);
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $alias = '';
        for ($i = 0; $i < 8; $i++) {
            $alias .= $chars[ord($hash[$i]) % 36];
        }

        // 按 Header 习惯首字母大写；首位若是数字则改成字母。
        $letters = 'abcdefghijklmnopqrstuvwxyz';
        if (!ctype_alpha($alias[0])) {
            $alias[0] = $letters[ord($hash[0]) % 26];
        }

        return ucfirst($alias);
    }
}
