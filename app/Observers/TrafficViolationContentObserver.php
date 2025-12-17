<?php

namespace App\Observers;

use App\Models\TrafficViolationContent;

class TrafficViolationContentObserver
{
    /**
     * Handle the TrafficViolationContent "created" event.
     */
    public function created(TrafficViolationContent $trafficViolationContent): void
    {
        //
    }

    /**
     * Handle the TrafficViolationContent "updated" event.
     */
    public function updated(TrafficViolationContent $trafficViolationContent): void
    {
//        logger()->info('---TrafficViolationContent-Updated---', $trafficViolationContent->getDirty());

        // 审核状态变化
        // if ($trafficViolationContent->isDirty('audit_status')) {
        //     $auditStatus = $trafficViolationContent['audit_status'];
        //
        //     $data['audit_time'] = now()->toDateTimeString();
        //     $data['audit_user_id'] = adminId() ?? 0;
        //     $data['notification_status'] = 1;
        //     // 审核通过
        //     if ($auditStatus == 1) {
        //         if (empty($trafficViolationContent['reply_content'])) {
        //             $data['reply_content'] = '正义从不缺席，请领取您的正能量';
        //         }
        //     }
        //
        //     // 审核不通过
        //     if ($auditStatus == 2) {
        //         if (empty($trafficViolationContent['reply_content'])) {
        //             $data['reply_content'] = '您好，您的举报已收到，行为已超时或已被提交，未能通过审核。感谢您的支持！';
        //         }
        //     }
        //
        //     \DB::table('traffic_violation_content')->where('id', $trafficViolationContent['id'])->update($data);
        // }
    }

    /**
     * Handle the TrafficViolationContent "deleted" event.
     */
    public function deleted(TrafficViolationContent $trafficViolationContent): void
    {
        //
    }

    /**
     * Handle the TrafficViolationContent "restored" event.
     */
    public function restored(TrafficViolationContent $trafficViolationContent): void
    {
        //
    }

    /**
     * Handle the TrafficViolationContent "force deleted" event.
     */
    public function forceDeleted(TrafficViolationContent $trafficViolationContent): void
    {
        //
    }
}
