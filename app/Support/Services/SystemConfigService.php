<?php

namespace App\Support\Services;

use App\Models\SystemConfig;

/** 获取系统配置服务类
 * Class SystemConfigServices
 *
 * @package service
 */
class SystemConfigService
{
    public static function cacheKey(int $appId): string
    {
        return sprintf('%s:app_id:%s', SystemConfig::cacheKey, $appId);
    }

    public static function appConfigCacheKey(int $appId): string
    {
        return sprintf('%s:app_id:%s', 'sys_app_config', $appId);
    }

    /**
     * 获取单个配置
     *
     * @param int $appId
     * @param string $key
     *
     * @return string
     */
    public static function get(int $appId, string $key): string
    {
        try {
            return redis()->hGet(self::cacheKey($appId), $key) ?? '';
        } catch (\Exception $e) {
            logger()->error($e->getMessage());

            return '';
        }
    }

    /**
     * 获取多个配置
     *
     * @param int $appId
     * @param array $keys 示例 [['appid','1'],'appKey']
     *
     * @return array
     */
    public static function more(int $appId, array $keys): array
    {
        try {
            return redis()->hMGet(self::cacheKey($appId), $keys) ?? [];
        } catch (\Exception $e) {
            logger()->error($e->getMessage());

            return [];
        }
    }

    /**
     * 获取应用配置
     */
    public static function getAppConfigs(int $appId): array
    {
        try {
            return redis()->hGetAll(self::appConfigCacheKey($appId)) ?? [];
        } catch (\Exception $e) {
            logger()->error($e->getMessage());

            return [];
        }
    }

    /**
     * 按应用缓存所有配置
     */
    public static function cacheByAppId(int $appId): array
    {
        $cacheKey = self::cacheKey($appId);
        $redis = redis();

        $configs = SystemConfig::query()->where('app_id', $appId)
            ->pluck('value', 'menu_name')
            ->toArray();

        try {
            $redis->del($cacheKey);
            if ($configs) {
                $redis->hMSet($cacheKey, $configs);
            }

            // 缓存应用配置数据
            self::cacheAppConfig($appId);
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
        }

        return $configs;
    }

    /**
     * 按应用缓存所有配置(app端配置信息)
     */
    public static function cacheAppConfig(int $appId): array
    {
        $cacheKey = self::appConfigCacheKey($appId);
        $redis = redis();

        $configs = SystemConfig::query()->where('app_id', $appId)
            ->where('status', 1)
            ->pluck('value', 'menu_name')
            ->toArray();

        try {
            $redis->del($cacheKey);
            if ($configs) {
                $redis->hMSet($cacheKey, $configs);
            }
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
        }

        return $configs;
    }
}

