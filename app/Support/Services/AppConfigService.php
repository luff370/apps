<?php

namespace App\Support\Services;

use App\Models\AppConfig;

class AppConfigService
{
    public static function cacheByAppId($appId): array
    {
        $cacheKey = sprintf(AppConfig::CACHE_BY_APPID, $appId);

        $configs = AppConfig::query()->where('app_id', $appId)
            ->where('is_enable', 1)
            ->get(['channel', 'version', 'key', 'value'])
            ->toArray();
        cache()->put($cacheKey, $configs, now()->addMonth());

        return $configs;
    }

    public static function getConfigsByAppId($appId)
    {
        $cacheKey = sprintf(AppConfig::CACHE_BY_APPID, $appId);

        $configs = cache($cacheKey);
        if ($configs === null) {
            $configs = AppConfig::query()->where('app_id', $appId)
                ->where('is_enable', 1)
                ->get(['channel', 'version', 'key', 'value'])
                ->toArray();

            cache()->put($cacheKey, $configs, now()->addMonth());
        }

        return $configs;
    }

    public static function getConfigsByAppIdChannelVersion($appId, $channel, $version): array
    {
        $configs = self::getConfigsByAppId($appId);
        if (empty($configs)) {
            return [];
        }

        $data = [];
        $configs = collect($configs)->groupBy('key')->all();
        foreach ($configs as $key => $items) {
            foreach ($items as $item) {
                if ($item['version'] == $version && $item['channel'] == $channel) {
                    $data[$key] = $item['value'];
                }
                if ($item['version'] == 'all' && $item['channel'] == $channel && !isset($data[$key])) {
                    $data[$key] = $item['value'];
                }
                if ($item['version'] == $version && $item['channel'] == 'all' && !isset($data[$key])) {
                    $data[$key] = $item['value'];
                }
                if ($item['version'] == 'all' && $item['channel'] == 'all' && !isset($data[$key])) {
                    $data[$key] = $item['value'];
                }
            }
        }

        return $data;
    }

}
