<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use App\Models\TrafficViolationContent;

class TrafficAuditSuccessful extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:traffic-audit-successful';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '违章内容自动审核,审核成功处理';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("违章内容审核成功定时任务开始执行:" . now()->toDateTimeString());
        logger()->info("违章内容审核成功定时任务开始执行:" . now()->toDateTimeString());

        $appId = 10002;
        $auditPassTimes = 9;
        $startTime = now()->subDays(9)->startOfDay()->toDateTimeString();
        $endTime = now()->subDays(2)->endOfDay()->toDateTimeString();

        $auditData['audit_time'] = now()->toDateTimeString();
        $auditData['audit_user_id'] = 0;
        $auditData['notification_status'] = 1;
        $auditData['is_top'] = 1;

        $trafficViolations = TrafficViolationContent::query()
            ->whereBetween('created_at', [$startTime, $endTime])
            ->where('audit_status', '=', 0)
            ->orderBy('id', 'desc')
            ->get(['id', 'user_id'])
            ->groupBy('user_id');

        foreach ($trafficViolations as $userId => $trafficViolation) {
            $trafficViolation = $trafficViolation->toArray();
            $trafficViolationIds = array_column($trafficViolation, 'id');
            shuffle($trafficViolationIds);

            // 当前用户积分余额验证
            // $userIntegral = User::query()
            //     ->where('id', '=', $userId)
            //     ->value('integral');

            // 当前用户已审核通过次数
            $userAuditPassCount = TrafficViolationContent::query()
                ->where('user_id', '=', $userId)
                ->where('audit_status', '=', 1)
                ->count();

            // 判断用户通过次数是否小于总次数,且用户积分余额小于90
            if ($userAuditPassCount < $auditPassTimes) {
                // 判断是不是首次审核，首次通过2条否则每次通过1条
                $times = $userAuditPassCount > 0 ? 1 : 2;
                $auditPassIds = [];
                for ($i = 0; $i < $times; $i++) {
                    if (!empty($trafficViolationIds)) {
                        $auditPassIds[] = array_pop($trafficViolationIds);
                    }
                }

                // 审核通过
                $auditData['audit_status'] = 1;
                $auditData['reply_content'] = '正义从不缺席，请领取您的正能量';
                TrafficViolationContent::query()
                    ->whereIn('id', $auditPassIds)
                    ->update($auditData);

                // 审核成功通知
                \App\Support\Utils\UserNotice::sendAuditSuccessNotice($appId, $userId);

                logger()->info("违章审核通过", ['user' => $userId, "auditPassIds" => $auditPassIds]);
            }
        }

        $this->info("违章内容审核任务完成:" . now()->toDateTimeString());
        logger()->info("违章内容审核任务完成:" . now()->toDateTimeString());
    }
}
