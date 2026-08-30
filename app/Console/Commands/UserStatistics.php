<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\SystemApp;
use App\Models\UserStatistic;
use Illuminate\Console\Command;
use App\Support\Traits\ServicesTrait;

class UserStatistics extends Command
{
    use ServicesTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user-statistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户统计定时任务';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("用户统计定时任务执行--" . now()->toDateTimeString());

        $service = $this->userStatisticsService();

        $date = today()->toDateString();
        $startTime = today()->startOfDay()->unix();
        $endTime = today()->endOfDay()->unix();
        $currentMinute = today()->addMinutes(10);

        $newUsers = User::query()->selectRaw("count(id) as count, app_id, IFNULL(market_channel, '') as market_channel")
            ->whereBetween('reg_time', [$startTime, $endTime])
            ->groupBy('app_id', 'market_channel')
            ->get();

        $newUsersByApp = [];
        $newUsersByAppChannel = [];
        foreach ($newUsers as $row) {
            $appId = (int)$row->app_id;
            $channel = strtolower(trim((string)$row->market_channel));
            $newUsersByApp[$appId] = ($newUsersByApp[$appId] ?? 0) + (int)$row->count;
            if ($channel === '') {
                continue;
            }
            $newUsersByAppChannel[$appId][$channel] = ($newUsersByAppChannel[$appId][$channel] ?? 0) + (int)$row->count;
        }

        $apps = SystemApp::query()->where('is_del', 0)->pluck('id')->toArray();

        $data = [];
        foreach ($apps as $appId) {
            $newUsersCount = $newUsersByApp[$appId] ?? 0;
            $activeUsersCount = $service->getActiveUserCount($appId);
            if ($newUsersCount > 0 || $activeUsersCount > 0) {
                $data[] = [
                    'app_id' => $appId,
                    'market_channel' => '',
                    'date' => $date,
                    'new_users_count' => $newUsersCount,
                    'active_users_count' => $activeUsersCount,
                ];
            }

            $channels = array_unique(array_merge(
                array_keys($newUsersByAppChannel[$appId] ?? []),
                $service->getActiveMarketChannels($appId)
            ));
            foreach ($channels as $channel) {
                $channelNewUsersCount = $newUsersByAppChannel[$appId][$channel] ?? 0;
                $channelActiveUsersCount = $service->getActiveUserCount($appId, $channel);
                if ($channelNewUsersCount == 0 && $channelActiveUsersCount == 0) {
                    continue;
                }

                $data[] = [
                    'app_id' => $appId,
                    'market_channel' => $channel,
                    'date' => $date,
                    'new_users_count' => $channelNewUsersCount,
                    'active_users_count' => $channelActiveUsersCount,
                ];
            }

            if ($currentMinute > now()) {
                // 每天00点 新增当天统计数据，并删除昨天的缓存数据
                $service->delUserActiveStatKey($appId, today()->subDay()->toDateString());
            }
        }
        if (!empty($data)) {
            UserStatistic::query()->upsert(
                $data,
                ['app_id', 'date', 'market_channel'],
                ['new_users_count', 'active_users_count']
            );
        }
    }
}
