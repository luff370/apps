<?php

namespace App\Support\Utils;

use App\Jobs\UserNoticePush;

class UserNotice
{
    public static function send($appId, $userId, $type, $title, $content)
    {
        $notice = \App\Models\UserNotice::query()->create([
            'user_id' => $userId,
            'app_id' => $appId,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'planned_push_time' => now(),
            'status' => 0,
        ]);

        UserNoticePush::dispatch($notice);

        return $notice;
    }

    //审核成功：
    //举报已通过审核
    //提交的举报已过审，打开领取正能量兑换奖励！
    //
    //审核失败：
    //审核失败通知
    //有新的举报被拒绝，点击查看
    //
    //提现成功：
    //提现成功
    //您提交的提现金额已到账，请注意查收！
    //
    //提现失败：
    //提现失败通知
    //您提交的提现未能成功，请点击查看原因！

    public static function sendAuditSuccessNotice($appId, $userId)
    {
        $title = '举报已通过审核';
        $content = '提交的举报已过审，打开领取正能量兑换奖励！';

        return self::send($appId, $userId, \App\Models\UserNotice::TypeAuditSuccessful, $title, $content);
    }

    public static function sendAuditFailNotice($appId, $userId)
    {
        $title = '审核失败通知';
        $content = '有新的举报被拒绝，点击查看';

        return self::send($appId, $userId, \App\Models\UserNotice::TypeAuditFailed, $title, $content);
    }

    public static function sendWithdrawalSuccessNotice($appId, $userId)
    {
        $title = '提现成功通知';
        $content = '您提交的提现金额已到账，请注意查收！';

        return self::send($appId, $userId, \App\Models\UserNotice::TypeWithdrawalSuccessful, $title, $content);
    }

    public static function sendWithdrawalFailNotice($appId, $userId)
    {
        $title = '提现失败通知';
        $content = '您提交的提现未能成功，请点击查看原因！';

        return self::send($appId, $userId, \App\Models\UserNotice::TypeWithdrawalFailed, $title, $content);
    }
}
