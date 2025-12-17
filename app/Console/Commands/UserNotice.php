<?php

namespace App\Console\Commands;

use App\Support\Utils\JPush;
use Illuminate\Console\Command;
use Hedeqiang\UMeng\Facades\Push;

class UserNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:user-notice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户推送';

    /**
     *
     * Execute the console command.
     */
    public function handle()
    {
        //审核成功：
        // 举报已通过审核
        // 提交的举报已过审，打开领取正能量兑换奖励！
        //
        // 审核失败：
        // 审核失败通知
        // 有新的举报被拒绝，点击查看
        //
        // 提现成功：
        // 提现成功
        // 您提交的提现金额已到账，请注意查收！
        //
        // 提现失败：
        // 提现失败通知
        // 您提交的提现未能成功，请点击查看原因！

        // $res  = JPush::Send('191e35f7e1777ccbdad','重要更新提醒',"推送内容");
        // dd($res);

       dd(\App\Support\Utils\UserNotice::sendAuditSuccessNotice(10002, 12316));
        // Android
        $params = [
            'type' => 'unicast',
            'device_tokens' => 'xx(Android为44位)',
            'payload' => [
                'display_type' => 'message',
                'body' => [
                    'custom' => '自定义custom',
                ],
            ],
            'policy' => [
                'expire_time' => '2013-10-30 12:00:00',
            ],
            'description' => '测试单播消息-Android',
        ];

        // iOS
        $params = [
            'type' => 'unicast',
            // 'device_tokens' => 'c97edb3e5db0898872ca579c38a0f37dce1035f0b26d70d1102d5ecae4d1267a',
            'device_tokens' => '759dc6cc159d5bf15c2fc34f103733ab2763ae53787ca83f3d994957e9d888f9',
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => '举报已通过审核',
                        // 'subtitle' => '快来领取奖励吧！',
                        'body' => '提交的举报已过审，打开领取正能量兑换奖励！',
                    ]
                ],
            ],
            'policy' => [
                'expire_time' => now()->addDay()->toDateTimeString(),
            ],
            'description' => '测试单播消息-iOS',
        ];

        $this->info('开始执行用户推送');

        $res = Push::ios()->send($params);

        dd($res);

        $this->info('执行完毕');

    }
}
