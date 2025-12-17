<?php

namespace App\Support\Services;

use App\Models\SystemGroup;
use App\Models\SystemGroupData;
use App\Services\System\Config\SystemGroupDataServices;

/**
 * 获取组合数据配置
 * Class GroupDataServices
 *
 * @package App\Support\Services
 */
class GroupDataService
{
    public static function cacheKey(string $configName): string
    {
        return sprintf("sys_group_data:%s", $configName);
    }

    /**
     * 获取单个值
     *
     * @param string $config_name 配置名称
     *
     * @return array
     */
    public static function getData(string $config_name): array
    {
        try {
            $configs = cache(self::cacheKey($config_name));
            if ($configs === null) {
                /** @var SystemGroupDataServices $service */
                $service = app(SystemGroupDataServices::class);

                $configs = $service->getConfigNameValue($config_name);
                cache()->put(self::cacheKey($config_name), $configs, now()->addMonth());
            }

            return $configs;
        } catch (\Exception $e) {
            logger()->error($e->getMessage());
            return [];
        }
    }

    public static function clearCache(string $config_name): bool
    {
        return cache()->forget(self::cacheKey($config_name));
    }

    public static function clearCacheByGroupId(int $groupId): bool
    {
        $config_name = SystemGroup::query()->where('id', $groupId)->value('config_name');
        if ($config_name) {
            return cache()->forget(self::cacheKey($config_name));
        }

        return false;
    }

}
