<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\SystemApp;
use App\Models\UserStatistic;
use App\Models\UserUuid;
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
     * 把当天注册、新增 UUID、活跃写入 user_statistics。
     *
     * 报表页目前仍现查底表；日表继续按同一口径沉淀，方便对照，数据稳定后再切日表。
     */
    public function handle()
    {
        $this->info("用户统计定时任务执行--" . now()->toDateTimeString());

        $service = $this->userStatisticsService();

        $date = today()->toDateString();
        $startTime = today()->startOfDay()->unix();
        $endTime = today()->endOfDay()->unix();
        $startAt = today()->startOfDay();
        $endAt = today()->endOfDay();

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

        $newUuids = UserUuid::query()
            ->selectRaw("count(*) as count, app_id, IFNULL(market_channel, '') as market_channel")
            ->whereBetween('created_at', [$startAt, $endAt])
            ->groupBy('app_id', 'market_channel')
            ->get();

        $newUuidsByApp = [];
        $newUuidsByAppChannel = [];
        foreach ($newUuids as $row) {
            $appId = (int)$row->app_id;
            $channel = strtolower(trim((string)$row->market_channel));
            $newUuidsByApp[$appId] = ($newUuidsByApp[$appId] ?? 0) + (int)$row->count;
            if ($channel === '') {
                continue;
            }
            $newUuidsByAppChannel[$appId][$channel] = ($newUuidsByAppChannel[$appId][$channel] ?? 0) + (int)$row->count;
        }

        $apps = SystemApp::query()->where('is_del', 0)->pluck('id')->toArray();

        $data = [];
        foreach ($apps as $appId) {
            $newUsersCount = $newUsersByApp[$appId] ?? 0;
            $activeUsersCount = $service->getActiveUserCount($appId);
            $newUuidCount = $newUuidsByApp[$appId] ?? 0;
            if ($newUsersCount > 0 || $activeUsersCount > 0 || $newUuidCount > 0) {
                $data[] = [
                    'app_id' => $appId,
                    'market_channel' => '',
                    'date' => $date,
                    'new_users_count' => $newUsersCount,
                    'new_uuid_count' => $newUuidCount,
                    'active_users_count' => $activeUsersCount,
                ];
            }

            $channels = array_unique(array_merge(
                array_keys($newUsersByAppChannel[$appId] ?? []),
                array_keys($newUuidsByAppChannel[$appId] ?? []),
                $service->getActiveMarketChannels($appId)
            ));
            foreach ($channels as $channel) {
                $channelNewUsersCount = $newUsersByAppChannel[$appId][$channel] ?? 0;
                $channelActiveUsersCount = $service->getActiveUserCount($appId, $channel);
                $channelNewUuidCount = $newUuidsByAppChannel[$appId][$channel] ?? 0;
                if ($channelNewUsersCount == 0 && $channelActiveUsersCount == 0 && $channelNewUuidCount == 0) {
                    continue;
                }

                $data[] = [
                    'app_id' => $appId,
                    'market_channel' => $channel,
                    'date' => $date,
                    'new_users_count' => $channelNewUsersCount,
                    'new_uuid_count' => $channelNewUuidCount,
                    'active_users_count' => $channelActiveUsersCount,
                ];
            }

            $service->delUserActiveStatKey($appId, today()->subDay()->toDateString());
        }
        if (!empty($data)) {
            UserStatistic::query()->upsert(
                $data,
                ['app_id', 'date', 'market_channel'],
                ['new_users_count', 'new_uuid_count', 'active_users_count']
            );
        }
    }
}
