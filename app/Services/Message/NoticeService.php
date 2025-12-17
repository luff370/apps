<?php

namespace App\Services\Message;

use App\Services\Service;
use App\Jobs\Notice\PrintJob;
use App\Exceptions\AdminException;
use App\Support\Services\CacheService;
use App\Jobs\Notice\EnterpriseWechatJob;

/**
 * 站内信services类
 * Class MessageSystemServices
 */
class NoticeService extends Service
{
    /**
     * 发送消息类型
     *
     * @var array
     */
    //    protected $type = [
    //        'is_sms' => SmsService::class,
    //        'is_system' => SystemSendServices::class,
    //        'is_wechat' => WechatTemplateService::class,
    //        'is_routine' => RoutineTemplateServices::class,
    //        'is_ent_wechat' => EntWechatServices::class,
    //    ];

    /**
     * @var array
     */
    protected $notceinfo;

    /**
     * @var string
     */
    protected $event;

    /**
     * @param string $event
     */
    public function setEvent(string $event)
    {
        if ($this->event != $event) {
            $this->notceinfo = CacheService::get('NOTCE_' . $event);
            if (!$this->notceinfo) {
                /** @var SystemNotificationServices $services */
                $services = app()->make(SystemNotificationServices::class);
                $notceinfo = $services->getOneNotce(['mark' => $event]);
                $this->notceinfo = $notceinfo ? $notceinfo->toArray() : [];
                CacheService::set('NOTCE_' . $event, $this->notceinfo);
            }
            $this->event = $event;
        }

        return $this;
    }

    /**
     * 打印订单
     *
     * @param $order
     * @param array $cartId
     */
    public function orderPrint($order)
    {
        /** @var StoreOrderCartInfoServices $cartServices */
        $cartServices = app()->make(StoreOrderCartInfoServices::class);
        $product = $cartServices->getCartInfoPrintProduct($order['cart_id']);
        if (!$product) {
            throw new AdminException(400463);
        }
        $configdata = [
            'clientId' => sys_config('printing_client_id', ''),
            'apiKey' => sys_config('printing_api_key', ''),
            'partner' => sys_config('develop_id', ''),
            'terminal' => sys_config('terminal_number', ''),
        ];
        $switch = (bool) sys_config('pay_success_printing_switch');
        if (!$switch) {
            throw new AdminException(400464);
        }
        if (!$configdata['clientId'] || !$configdata['apiKey'] || !$configdata['partner'] || !$configdata['terminal']) {
            throw new AdminException(400465);
        }
        PrintJob::dispatch('doJob', ['yi_lian_yun', $configdata, $order, $product]);
    }
}
