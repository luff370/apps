<?php

namespace App\Support\Utils;

use Overtrue\EasySms\EasySms;
use App\Models\System\SmsRecord;
use App\Exceptions\AdminException;

class SMS
{
    private static $app = null;

    public static function app(): EasySms
    {
        if (self::$app != null) {
            return self::$app;
        }

        self::$app = new EasySms(config('sms'));

        return self::$app;
    }

    /**
     * 订单物流消息通知
     *
     * @throws \App\Exceptions\AdminException
     */
    public static function expressNotify($phone, $goods, $expressNo, $expressName = '', $ticket = ''): array
    {
        $easySms = self::app();
        $goods = mb_substr($goods, 0, 19);
        $expressName = mb_substr($expressName, 0, 4);

        switch (config('sms.default_gateway')) {
            case 'aliyun':
                $goods = $goods . (empty($expressName) ? ".." : "..[$expressName]");
                $smsData = [
                    'template' => 'SMS_265475359',
                    'data' => [
                        'goods' => $goods,
                        'express_no' => $expressNo,
                    ],
                ];
                $content = "您购买的商品{$goods}已发货，快递单号，快递单号：{$expressNo}，请您注意收货。";
                break;
            case 'yunxin':
                if (!empty($ticket)) {
                    $smsData = [
                        'template' => '22525445',
                        'data' => [
                            'action' => 'sendTemplate',
                            'params' => json_encode([$goods, $expressName, $expressNo, $ticket]),
                        ],
                    ];
                    $content = "您购买的 {$goods} 订单是使用{$expressName}运输，单号：{$expressNo}。点 p3x.cn/{$ticket} 查看订单及最新物流信息。";
                } else {
                    $smsData = [
                        'template' => '22520563',
                        'data' => [
                            'action' => 'sendTemplate',
                            'params' => json_encode([$goods, $expressName, $expressNo]),
                        ],
                    ];
                    $content = "您购买的 {$goods} 已通过{$expressName}发货，快递单号：{$expressNo}，请您注意收货。";
                }

                break;
            default:
                throw new AdminException('请配置可用的短信发送网关');
        }

        try {
            $res = $easySms->send($phone, $smsData);

            // 保存记录
            SmsRecord::query()->create([
                'type' => 'deliver_notify',
                'phone' => $phone,
                'content' => $content,
                'template' => $smsData['template'],
                'add_time' => time(),
                'resultcode' => 'OK',
            ]);

            return $res;
        } catch (\Exception $exception) {
            logger()->error('短信发送失败' . $exception->getMessage(), [$exception->getLastException()->getMessage()]);
            throw new AdminException('短信发送失败');
        }
    }

    /**
     * 订单发货通知
     *
     * @throws \App\Exceptions\AdminException
     */
    public static function deliverNotify($phone, $goods, $expressNo, $expressName = '', $ticket = ''): array
    {
        $easySms = self::app();
        $goods = mb_substr($goods, 0, 19);
        $expressName = mb_substr($expressName, 0, 4);

        switch (config('sms.default_gateway')) {
            case 'aliyun':
                $goods = $goods . (empty($expressName) ? ".." : "..[$expressName]");
                $smsData = [
                    'template' => 'SMS_265475359',
                    'data' => [
                        'goods' => $goods,
                        'express_no' => $expressNo,
                    ],
                ];
                $content = "您购买的商品{$goods}已发货，快递单号，快递单号：{$expressNo}，请您注意收货。";
                break;
            case 'yunxin':
                if (!empty($ticket)) {
                    $smsData = [
                        'template' => '22526837',
                        'data' => [
                            'action' => 'sendTemplate',
                            'params' => json_encode([$goods, $expressName, $expressNo, $ticket]),
                        ],
                    ];
                    $content = "您购买的 {$goods} 已通过{$expressName}发货，快递单号: {$expressNo}，请您注意收货。点 p3x.cn/{$ticket} 查看订单及物流信息。";
                } else {
                    $smsData = [
                        'template' => '22520563',
                        'data' => [
                            'action' => 'sendTemplate',
                            'params' => json_encode([$goods, $expressName, $expressNo]),
                        ],
                    ];
                    $content = "您购买的 {$goods} 已通过{$expressName}发货，快递单号：{$expressNo}，请您注意收货。";
                }

                break;
            default:
                throw new AdminException('请配置可用的短信发送网关');
        }

        try {
            $res = $easySms->send($phone, $smsData);

            // 保存记录
            SmsRecord::query()->create([
                'type' => 'deliver_notify',
                'phone' => $phone,
                'content' => $content,
                'template' => $smsData['template'],
                'add_time' => time(),
                'resultcode' => 'OK',
            ]);

            return $res;
        } catch (\Exception $exception) {
            logger()->error('短信发送失败' . $exception->getMessage(), [$exception->getLastException()->getMessage()]);
            throw new AdminException('短信发送失败');
        }
    }

    /**
     * 包裹取件通知
     *
     * @throws \App\Exceptions\AdminException
     */
    public static function pickUpNotify($phone, $goods, $expressInfo, $ticket = ''): array
    {
        $easySms = self::app();
        $goods = mb_substr($goods, 0, 19);

        switch (config('sms.default_gateway')) {
            case 'yunxin':
                if (!empty($ticket)) {
                    $smsData = [
                        'template' => '22526838',
                        'data' => [
                            'action' => 'sendTemplate',
                            'params' => json_encode([$goods, $expressInfo, $ticket]),
                        ],
                    ];
                    $content = "您购买的 {$goods} 已送至{$expressInfo}，请及时取件。如已取件，请忽略。点 p3x.cn/{$ticket} 查看订单及物流信息。";
                } else {
                    $smsData = [
                        'template' => '22521471',
                        'data' => [
                            'action' => 'sendTemplate',
                            'params' => json_encode([$goods, $expressInfo]),
                        ],
                    ];
                    $content = "您购买的 {$goods} 已送至{$expressInfo}，请及时取件。如已取件，请忽略。";
                }

                break;
            default:
                throw new AdminException('请配置可用的短信发送网关');
        }

        try {
            $res = $easySms->send($phone, $smsData);

            // 保存记录
            SmsRecord::query()->create([
                'type' => 'pick_up_notify',
                'phone' => $phone,
                'content' => $content,
                'template' => $smsData['template'],
                'add_time' => time(),
                'resultcode' => 'OK',
            ]);

            return $res;
        } catch (\Exception $exception) {
            logger()->error('短信发送失败' . $exception->getMessage(), [$exception->getLastException()->getMessage()]);
            throw new AdminException('短信发送失败');
        }
    }

    /**
     * 发送验证码
     *
     * @param $phone
     * @param $code
     *
     * @return array
     * @throws \Overtrue\EasySms\Exceptions\InvalidArgumentException
     * @throws \Overtrue\EasySms\Exceptions\NoGatewayAvailableException
     */
    public static function sendCode($phone, $code): array
    {
        $easySms = self::app();

        return $easySms->send($phone, [
            'template' => '22521470',    // 不填则使用默认模板
            'data' => [
                'code' => $code,        // 如果设置了该参数，则 code_length 参数无效
                'action' => 'sendCode', // 默认为 `sendCode`，校验短信验证码使用 `verifyCode`
            ],
        ]);
    }
}
