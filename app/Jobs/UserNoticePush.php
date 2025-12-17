<?php

namespace App\Jobs;

use App\Models\UserNotice;
use App\Models\DeviceToken;
use App\Support\Utils\JPush;
use App\Support\Utils\UPush;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UserNoticePush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected UserNotice $userNotice;

    /**
     * Create a new job instance.
     */
    public function __construct(UserNotice $userNotice)
    {
        $this->userNotice = $userNotice;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $appId = $this->userNotice->app_id;

        // TODO 这里的判断，是否开启推送，暂时先注释掉，因为目前获取配置有问题。队列任务是同步推送，后期可能需要改为异步
        // 判断是否开启推送
        // $isOpen = sys_config('push_switch', $appId);
        // if (!$isOpen) {
        //     return;
        // }

        // 用户信息推送处理
        $this->userNotice->push_time = now();

        try {
            $user = $this->userNotice->user;
            $deviceToken = DeviceToken::query()->where('uuid', $user->uuid)->value('u_token');
            if (empty($deviceToken)) {
                logger()->error('用户消息推送失败--' . "token 不存在，UUID:" . $user->uuid);
                $this->userNotice->error_msg = "token 不存在";
                $this->userNotice->status = 2;
                $this->userNotice->save();

                return;
            }

            $platform = $user['platform'];
            $title = $this->userNotice['title'];
            $body = $this->userNotice['content'];

            // 平台字段过滤处理，防止前端传值错误
            switch ($appId) {
                case 10002:
                    $platform = 'ios';
                    break;
                case 10016:
                    $platform = 'android';
                    break;
            }

            $res = UPush::Send($appId, $deviceToken, $title, $body, $platform);
            if ($res['ret'] == 'SUCCESS') {
                $this->userNotice->status = 1;
                $this->userNotice->msg_id = $res['data']['msg_id'] ?? '';
            } else {
                $this->userNotice->status = 2;
                $this->userNotice->error_msg = $res['data']['error_msg'] ?? '';
            }
        } catch (\Exception $exception) {
            logger()->error('用户消息推送失败--' . $exception->getMessage());
            $this->userNotice->error_msg = $exception->getMessage();
            $this->userNotice->status = 2;
        }

        $this->userNotice->save();
    }
}
