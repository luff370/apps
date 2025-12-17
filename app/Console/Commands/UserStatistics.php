<?php

namespace App\Console\Commands;

use App\Models\SystemApp;
use App\Models\User;
use App\Models\UserStatistic;
use App\Support\Traits\ServicesTrait;
use Illuminate\Console\Command;

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

        $newUsers = User::query()->selectRaw("count(id) as count, app_id")
            ->whereBetween('reg_time', [$startTime, $endTime])
            ->groupBy('app_id')
            ->get();

        foreach ($newUsers as $item) {
            $appId = $item->app_id;
            $newUsersCount = $item->count;
            $activeUsersCount = $service->getActiveUserCount($appId);

            if ($currentMinute > now()) {
                // 每天00点 新增当天统计数据，并删除昨天的缓存数据
                $service->delUserActiveStatKey($appId, today()->subDay()->toDateString());
                UserStatistic::query()->updateOrCreate(['app_id' => $appId, 'date' => $date], ['new_users_count' => $newUsersCount, 'active_users_count' => $activeUsersCount]);
            } else {
                // 更新数据
                UserStatistic::query()->where('app_id', $appId)->where('date', $date)->update(['active_users_count' => $activeUsersCount, 'new_users_count' => $newUsersCount]);
            }
        }
    }
}
