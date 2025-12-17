<?php

namespace App\Support\Services;

use App\Models\AppAdvertisement;

class Advertisement
{
    public static function cacheByAppId($appId): array
    {
        $cacheKey = sprintf(AppAdvertisement::CACHE_BY_APPID, $appId);

        $configs = AppAdvertisement::query()->where('app_id', $appId)
            ->where('status', 1)
            ->get(['title', 'market_channel', 'position', 'type', 'channels'])
            ->toArray();
        cache()->put($cacheKey, $configs, now()->addMonth());

        return $configs;
    }

    public static function getAdvertisementsByAppId($appId)
    {
        $cacheKey = sprintf(AppAdvertisement::CACHE_BY_APPID, $appId);

        $configs = cache($cacheKey);
        if ($configs === null) {
            $configs = AppAdvertisement::query()->where('app_id', $appId)
                ->where('status', 1)
                ->get(['title', 'market_channel', 'position', 'type', 'channels'])
                ->toArray();
            cache()->put($cacheKey, $configs, now()->addMonth());
        }

        return $configs;
    }

    public static function getAdvertisementsByAppIdChannel($appId, $channel): array
    {
        $configs = self::getAdvertisementsByAppId($appId);
        if (empty($configs)) {
            return [];
        }

        $data = [];
        $configs = collect($configs)->groupBy('position')->all();
        foreach ($configs as $key => $items) {
            foreach ($items as $item) {
                if ($item['market_channel'] == $channel) {
                    $data[$key] = $item;
                }
                if ($item['market_channel'] == 'all' && !isset($data[$key])) {
                    $data[$key] = $item;
                }
            }
        }

        return $data;
    }

}
