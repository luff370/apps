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
     * 生成指定应用、指定日期、指定渠道的活跃用户 Redis key。
     *
     * 空渠道表示应用合计，key 格式与历史数据保持一致，避免升级当天 Redis 里已有集合失效。
     */
    public function getUserActiveStatKey($appId, $date = null, $marketChannel = ''): string
    {
        $date = $date ?? today()->toDateString();
        $marketChannel = $this->normalizeMarketChannel($marketChannel);
        if ($marketChannel === '') {
            return sprintf('user_active_stat:%s-%s', $appId, $date);
        }

        return sprintf('user_active_stat:%s:%s-%s', $appId, $marketChannel, $date);
    }

    /**
     * 当天出现过的应用市场集合，供定时任务按渠道落库。
     */
    public function getUserActiveChannelIndexKey($appId, $date = null): string
    {
        return sprintf('user_active_stat_channels:%s-%s', $appId, $date ?? today()->toDateString());
    }

    /**
     * 记录一次用户活跃。
     *
     * 同时写入应用合计 Set 和渠道 Set，同一 uuid 在同一天多次访问仍只算 1 个活跃。
     */
    public function userActiveStat($uuid, $appId, $marketChannel = ''): void
    {
        $this->redisClient->sAdd($this->getUserActiveStatKey($appId), $uuid);

        $marketChannel = $this->normalizeMarketChannel($marketChannel);
        if ($marketChannel === '') {
            return;
        }

        $this->redisClient->sAdd($this->getUserActiveStatKey($appId, null, $marketChannel), $uuid);
        $this->redisClient->sAdd($this->getUserActiveChannelIndexKey($appId), $marketChannel);
    }

    /**
     * 获取当前应用当天实时活跃人数。
     *
     * 空渠道返回应用合计；指定渠道返回该应用市场的去重人数。
     */
    public function getActiveUserCount($appId, $marketChannel = ''): int
    {
        return (int)$this->redisClient->sCard($this->getUserActiveStatKey($appId, null, $marketChannel));
    }

    /**
     * 当天已产生过活跃记录的应用市场列表。
     */
    public function getActiveMarketChannels($appId, $date = null): array
    {
        $members = $this->redisClient->sMembers($this->getUserActiveChannelIndexKey($appId, $date)) ?: [];

        return array_values(array_unique(array_filter(array_map([$this, 'normalizeMarketChannel'], $members))));
    }

    /**
     * 删除指定日期的活跃统计缓存。
     *
     * 通常在当天活跃数已经写入 user_statistics 后调用，避免 Redis 中临时 Set 无限堆积。
     */
    public function delUserActiveStatKey($appId, $date = null): void
    {
        $date = $date ?? today()->toDateString();
        $keys = [
            $this->getUserActiveStatKey($appId, $date),
            $this->getUserActiveChannelIndexKey($appId, $date),
        ];
        foreach ($this->getActiveMarketChannels($appId, $date) as $marketChannel) {
            $keys[] = $this->getUserActiveStatKey($appId, $date, $marketChannel);
        }

        foreach ($keys as $key) {
            $this->redisClient->del($key);
        }
    }

    /**
     * 获取用户趋势图数据。
     *
     * 按日期聚合新增和活跃人数；只读 market_channel 为空的应用合计行，避免与分渠道行加总重复。
     * app_id 为 0 时表示全应用汇总，指定 app_id 时用于单应用看板。
     */
    public function userCharts($days, $appId = 0)
    {
        return UserStatistic::query()->when($appId > 0, function ($query) use ($appId) {
            $query->where('app_id', $appId);
        })
            ->where('market_channel', '')
            ->selectRaw('date, sum(new_users_count) as new_users_count, sum(active_users_count) as active_users_count')
            ->orderBy('date', 'desc')
            ->groupBy('date')
            ->limit($days)
            ->get();
    }

    private function normalizeMarketChannel($marketChannel): string
    {
        return strtolower(trim((string)$marketChannel));
    }

}
