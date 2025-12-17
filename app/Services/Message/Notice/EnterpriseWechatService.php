<?php

namespace App\Services\Message\Notice;

use Illuminate\Support\Facades\Log;
use App\Services\Message\NoticeService;
use App\Jobs\Notice\EnterpriseWechatJob;

/**
 * 企业微信发送消息
 * Created by PhpStorm.
 * User: xurongyao <763569752@qq.com>
 * Date: 2021/9/22 1:23 PM
 */
class EnterpriseWechatService extends NoticeService
{
    /**
     * 判断是否开启权限
     *
     * @var bool
     */
    private $isopend = true;

    /**
     * 是否开启权限
     *
     * @param string $mark
     *
     * @return $this
     */
    public function isOpen(string $mark)
    {
        $this->isopend = $this->notceinfo['is_ent_wechat'] == 1 && $this->notceinfo['url'] !== '';

        return $this;
    }

    /**
     * 发送消息
     *
     * @param $uid uid
     * @param array $data 模板内容
     */
    public function sendMsg($data)
    {
        return true;

        try {
            if ($this->isopend) {
                $url = $this->notceinfo['url'];
                $ent_wechat_text = $this->notceinfo['ent_wechat_text'];
                EnterpriseWechatJob::dispatchDo('doJob', [$data, $url, $ent_wechat_text]);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return true;
        }
    }
}
