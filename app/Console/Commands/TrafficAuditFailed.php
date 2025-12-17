<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TrafficViolationContent;

class TrafficAuditFailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:traffic-audit-failed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '违章内容自动审核，失败处理';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("违章内容审核失败处理，定时任务开始执行:" . now()->toDateTimeString());
        logger()->info("违章内容审核失败处理，定时任务开始执行:" . now()->toDateTimeString());

        $appId = 10002;

        // 当天最后一次失败审核，不限制条数否则每次过期一条
        $dayLastAuditHour = 21;
        $currentHour = now()->hour;
        $times = $currentHour >= $dayLastAuditHour ? 0 : 1;

        $auditData['audit_time'] = now()->toDateTimeString();
        $auditData['audit_user_id'] = 0;
        $auditData['notification_status'] = 1;
        $auditData['is_top'] = 1;
        $auditData['audit_status'] = 2;
        $auditData['reply_content'] = '您好，您的举报已收到，行为已超时或已被提交，未能通过审核。感谢您的支持！';

        // 过期时间
        $expireTime = now()->subDays(8)->startOfDay()->toDateTimeString();
        $trafficViolations = TrafficViolationContent::query()
            ->where("created_at", "<", $expireTime)
            ->where("audit_status", 0)
            ->get(['id', 'user_id'])
            ->groupBy('user_id');

        foreach ($trafficViolations as $userId => $trafficViolation) {
            $trafficViolation = $trafficViolation->toArray();
            $trafficViolationIds = array_column($trafficViolation, 'id');

            // 等于0为不限制处理条数
            if ($times == 0) {
                $auditIds = $trafficViolationIds;
            } else {
                $auditIds = [];
                for ($i = 0; $i < $times; $i++) {
                    if (!empty($trafficViolationIds)) {
                        $auditIds[] = array_pop($trafficViolationIds);
                    }
                }
            }

            TrafficViolationContent::query()
                ->whereIn('id', $auditIds)
                ->update($auditData);

            // 发送通知
            \App\Support\Utils\UserNotice::sendAuditFailNotice($appId, $userId);

            logger()->info("违章审核不通过", ['user' => $userId, "auditPassIds" => $auditIds]);
        }

        $this->info("违章内容审核，失败处理任务完成:" . now()->toDateTimeString());
        logger()->info("违章内容审核，失败处理任务完成:" . now()->toDateTimeString());
    }
}
