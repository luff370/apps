<?php

namespace App\Observers;

use App\Models\AppAdvertisement;
use App\Support\Services\Advertisement;

class AppAdvertisementObserver
{
    /**
     * Handle the AppAdvertisement "created" event.
     */
    public function created(AppAdvertisement $appAdvertisement): void
    {
        logger()->info("应用广告配置新增事件", $appAdvertisement->toArray());

        // 更新列表缓存
        Advertisement::cacheByAppId($appAdvertisement->app_id);
    }

    /**
     * Handle the AppAdvertisement "updated" event.
     */
    public function updated(AppAdvertisement $appAdvertisement): void
    {
        logger()->info("应用广告配置更新事件", $appAdvertisement->toArray());

        // 更新列表缓存
        $oldAppId = $appAdvertisement->getOriginal('app_id');
        $appId = $appAdvertisement->app_id;

        if ($oldAppId !== $appId) {
            Advertisement::cacheByAppId($oldAppId);
        }
        Advertisement::cacheByAppId($appId);
    }

    /**
     * Handle the AppAdvertisement "deleted" event.
     */
    public function deleted(AppAdvertisement $appAdvertisement): void
    {
        logger()->info("应用广告删除事件", $appAdvertisement->toArray());

        // 更新列表缓存
        Advertisement::cacheByAppId($appAdvertisement->app_id);
    }

    /**
     * Handle the AppAdvertisement "restored" event.
     */
    public function restored(AppAdvertisement $appAdvertisement): void
    {
        //
    }

    /**
     * Handle the AppAdvertisement "force deleted" event.
     */
    public function forceDeleted(AppAdvertisement $appAdvertisement): void
    {
        //
    }
}
