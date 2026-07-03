<?php

namespace App\Services\Statistics;

use App\Models\UserStatistic;
use App\Services\Service;
use Illuminate\Support\Facades\Redis;

class UserStatisticsService extends Service
{
    private mixed $redisClient;

    /**
     * 用户活跃统计使用 Redis Set 暂存当天活跃设备/用户标识。
     *
     * Set 天然去重，适合“同一个用户当天多次访问只算一次活跃”的口径；后续定时任务可以把
     * Set 的基数汇总到 user_statistics 日表里，供首页和报表页快速查询。
     */
    public function __construct()
    {
        $this->redisClient = Redis::connection()->client();
    }

    /**
     * 生成指定应用、指定日期的活跃用户 Redis key。
     *
     * key 里带 app_id 和日期，避免多应用之间互相污染，也方便按天清理历史临时数据。
     */
    public function getUserActiveStatKey($appId, $date = null): string
    {
        return sprintf('user_active_stat:%s-%s', $appId, $date ?? today()->toDateString());
    }

    /**
     * 记录一次用户活跃。
     *
     * 这里写入的是 Redis Set，不直接累加数字，是为了同一 uuid 在同一天多次访问仍只算 1 个活跃。
     */
    public function userActiveStat($uuid, $appId): void
    {
        $cacheKey = $this->getUserActiveStatKey($appId);

        $this->redisClient->sAdd($cacheKey, $uuid);
    }

    /**
     * 获取当前应用当天实时活跃人数。
     *
     * 这个值用于实时看板或日统计落库前的临时查询，最终历史报表仍以 user_statistics 日表为主。
     */
    public function getActiveUserCount($appId): int
    {
        $cacheKey = $this->getUserActiveStatKey($appId);

        return (int)$this->redisClient->sCard($cacheKey);
    }

    /**
     * 删除指定日期的活跃统计缓存。
     *
     * 通常在当天活跃数已经写入 user_statistics 后调用，避免 Redis 中临时 Set 无限堆积。
     */
    public function delUserActiveStatKey($appId, $date = null): void
    {
        $cacheKey = $this->getUserActiveStatKey($appId, $date);

        $this->redisClient->del($cacheKey);
    }

    /**
     * 获取用户趋势图数据。
     *
     * 按日期聚合新增和活跃人数；app_id 为 0 时表示全应用汇总，指定 app_id 时用于单应用看板。
     */
    public function userCharts($days, $appId = 0)
    {
        return UserStatistic::query()->when($appId > 0, function ($query) use ($appId) {
            $query->where('app_id', $appId);
        })
            ->selectRaw('date, sum(new_users_count) as new_users_count, sum(active_users_count) as active_users_count')
            ->orderBy('date', 'desc')
            ->groupBy('date')
            ->limit($days)
            ->get();
    }

}
