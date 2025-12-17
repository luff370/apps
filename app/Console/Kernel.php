<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 每分钟执行用户统计定时任务
        $schedule->command('app:user-statistics')->everyFiveMinutes();

        // 每天 8:00 12:00 21:00 执行违章举报内容审核操作
        $schedule->command('app:traffic-audit-successful')->dailyAt('08:00');
        $schedule->command('app:traffic-audit-failed')->dailyAt('08:30');
        $schedule->command('app:traffic-audit-failed')->dailyAt('12:00');
        $schedule->command('app:traffic-audit-failed')->dailyAt('21:00');

        // 每天1:30执行违章举报图片删除操作
        $schedule->command('app:traffic-images-delete')->dailyAt('01:30');

        // 每天6:00-24:00 每两小时执行违章举报内容重新排序操作
        $schedule->command('app:traffic-violation-generate-sort')->between('06:00', '00:00')->everyTwoHours();

        // 每天6:00-24:00 每两10分钟执行文章内容重新排序操作
        // $schedule->command('app:article-generate-showtime')->between('06:00', '00:00')->everyTenMinutes();

        // 每天10点执行自动转账定时任务
        $schedule->command('app:user-withdrawal-auto-transfer')->dailyAt('10:00');

        // 每天1:00执行订阅用户状态检测定时任务
        $schedule->command('subscriptions:check')->dailyAt('01:00');

        // 每天00:00执行用户会员状态变更定时任务
        $schedule->command('app:member-status-auto-update')->dailyAt('00:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
