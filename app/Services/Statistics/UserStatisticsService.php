<?php

namespace App\Services\Statistics;

use App\Models\UserStatistic;
use App\Services\Service;
use Illuminate\Support\Facades\Redis;

class UserStatisticsService extends Service
{
    private mixed $redisClient;

    public function __construct()
    {
        $this->redisClient = Redis::connection()->client();
    }

    public function getUserActiveStatKey($appId, $date = null): string
    {
        return sprintf('user_active_stat:%s-%s', $appId, $date ?? today()->toDateString());
    }

    public function userActiveStat($uuid, $appId): void
    {
        $cacheKey = $this->getUserActiveStatKey($appId);

        $this->redisClient->sAdd($cacheKey, $uuid);
    }

    public function getActiveUserCount($appId): int
    {
        $cacheKey = $this->getUserActiveStatKey($appId);

        return (int)$this->redisClient->sCard($cacheKey);
    }

    public function delUserActiveStatKey($appId, $date = null): void
    {
        $cacheKey = $this->getUserActiveStatKey($appId, $date);

        $this->redisClient->del($cacheKey);
    }

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
