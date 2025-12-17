<?php

namespace App\Support\Utils;

use App\Support\Services\HttpService;

class FlyBookNotification
{
    public static function send($url, $content, $title = '', $msgType = 'text')
    {
        switch ($msgType) {
            case 'text': //普通文本

                if (is_array($content)) {
                    $content = implode("\n", $content);
                }

                $notifyData = [
                    "msg_type" => "text",
                    "content" => [
                        "text" => $content,
                    ],
                ];

                break;
            case 'post': //富文本
                if (empty($title)) {
                    if (is_array($content) && count($content) > 1) {
                        $title = array_shift($content);
                    } else {
                        $title = '消息提醒';
                    }
                }

                $notifyInfo = [];
                $content = \Illuminate\Support\Arr::wrap($content);
                foreach ($content as $item) {
                    $notifyInfo[] = [
                        'tag' => 'text',
                        'text' => $item,
                    ];
                }

                $notifyData = [
                    "msg_type" => "post",
                    "content" => [
                        "post" => [
                            "zh_cn" => [
                                "title" => $title,
                                "content" => [$notifyInfo],
                            ],
                        ],
                    ],
                ];
                break;

            default:
                return;
        }

        HttpService::postJson($url, json_encode($notifyData));
    }

    public static function OrderAddressException($order)
    {
        $isNotify = config('mall.address_exception_notify');
        $notifyUrl = config('mall.address_exception_notify_url');
        if ($isNotify && !empty($notifyUrl)) {
            $notifyInfo = [
                sprintf("订单号：%s \n", $order['order_id'] ?? ''),
                sprintf("省市区：%s %s  %s \n", $order['province'] ?? '', $order['city'] ?? '', $order['district'] ?? ''),
                sprintf("省市区：%s %s  %s \n", $order['province'] ?? '', $order['city'] ?? '', $order['district'] ?? ''),
            ];

            self::send($notifyUrl, $notifyInfo, '用户收货地址异常通知', 'post');
        }
    }
}
