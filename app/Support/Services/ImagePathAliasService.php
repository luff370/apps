<?php

namespace App\Support\Services;

/**
 * 图片路径别名：算法与接口别名 stableUrlAlias 一致，
 * 由 应用ID + 包名 + 真实路径前缀 现场算出 8 位稳定值，不落库、不走缓存。
 *
 * 只替换 storage/attach 这一段，后面的 年/月/文件名 原样保留，
 * 所以 nginx 只按形状就能还原出真实路径，新增应用不用改配置。
 */
class ImagePathAliasService
{
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
        for ($i = 0; $i < 8; $i++) {
            $alias .= $chars[ord($hash[$i]) % 36];
        }

        return $alias;
    }
}
