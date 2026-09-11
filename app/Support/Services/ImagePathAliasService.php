<?php

namespace App\Support\Services;

/**
 * 图片路径别名：算法与接口别名 stableUrlAlias 同源（HMAC-SHA256），
 * 由 应用ID + 包名 + 真实路径前缀 现场算出 32 位稳定值，不落库、不走缓存。
 *
 * SHA256 本身就是 32 字节，只是把哈希结果多取几位，不再额外计算。
 * 只替换命中的路径前缀，后面的日期/文件名原样保留，
 * nginx 按形状还原真实路径即可，新增应用不用改配置。
 */
class ImagePathAliasService
{
    private const ALIAS_LENGTH = 32;
    public function make(int $appId, string $packageName, string $pathPrefix): string
    {
        return $this->stableUrlAlias($this->identity($appId, $packageName, $pathPrefix));
    }

    /**
     * 把命中的真实前缀换成别名段，其余部分保持不变。
     */
    public function replacePrefix(string $path, string $matchedPrefix, int $appId, string $packageName): string
    {
        if (!str_starts_with($path, $matchedPrefix)) {
            return $path;
        }

        $alias = $this->make($appId, $packageName, $matchedPrefix);
        $tail = ltrim(substr($path, strlen($matchedPrefix)), '/');
        $leadingSlash = str_starts_with($path, '/') ? '/' : '';

        return $leadingSlash . $alias . ($tail !== '' ? '/' . $tail : '');
    }

    private function identity(int $appId, string $packageName, string $pathPrefix): string
    {
        $prefix = trim(str_replace('\\', '/', $pathPrefix), '/');

        return $appId . '|' . trim($packageName) . '|image_path|' . $prefix;
    }

    private function stableUrlAlias(string $identity, int $salt = 0): string
    {
        $key = 'api_alias|' . $identity;
        $hash = hash_hmac('sha256', 'url' . ($salt > 0 ? '|' . $salt : ''), $key, true);
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $alias = '';
        for ($i = 0; $i < self::ALIAS_LENGTH; $i++) {
            $alias .= $chars[ord($hash[$i]) % 36];
        }

        return $alias;
    }
}
