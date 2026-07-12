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

        $newUsers = User::query()->selectRaw("count(id) as count, app_id")
            ->whereBetween('reg_time', [$startTime, $endTime])
            ->groupBy('app_id')
            ->get()
            ->pluck('count', 'app_id');

        $apps = SystemApp::query()->where('is_del', 0)->pluck('id')->toArray();

        foreach ($apps as $appId) {
            $newUsersCount = $newUsers[$appId] ?? 00;
            $activeUsersCount = $service->getActiveUserCount($appId);
            if ($newUsersCount == 0 && $activeUsersCount == 0) {
                continue;
            }

            UserStatistic::query()->updateOrCreate(['app_id' => $appId, 'date' => $date], ['new_users_count' => $newUsersCount, 'active_users_count' => $activeUsersCount]);
            if ($currentMinute > now()) {
                // 每天00点 新增当天统计数据，并删除昨天的缓存数据
                $service->delUserActiveStatKey($appId, today()->subDay()->toDateString());
            }
        }
    }
}
