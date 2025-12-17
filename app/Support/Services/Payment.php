<?php

namespace App\Support\Services;

use Yansongda\Pay\Pay;
use App\Models\AppPayment;
use App\Models\SystemPayment;
use App\Exceptions\ApiException;
use App\Exceptions\AdminException;

class Payment
{
    const PAY_CHANNEL_WX = 'wechat';

    const PAY_CHANNEL_ALIPAY = 'alipay';

    const PAY_TYPE_APP = 'app';

    const PAY_TYPE_H5 = 'h5';

    const PAY_TYPE_MINI = 'mini';

    public static function getWechatConfigs(): array
    {
        $configs = [];
        $payments = SystemPayment::query()->where('type', SystemPayment::PayTypeWechat)->where('is_enable', true)->get();
        foreach ($payments as $payment) {
            $configs[$payment['mch_id']] = [
                'mch_id' => $payment['mch_id'],
                'mch_secret_key' => $payment['api_key'],
                'mch_secret_cert' => str_replace(['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n"], '', $payment['private_key']),
                'mch_public_cert_path' => storage_path() . $payment['mch_public_cert'],
                'mp_app_id' => '',
                'mini_app_id' => '',
            ];
        }

        $appPayments = AppPayment::query()->where('pay_type', SystemPayment::PayTypeWechat)->where('status', true)->get();
        foreach ($appPayments as $appPayment) {
            switch ($appPayment['app_type']) {
                case 'h5':
                case 'wechat':
                    $configs[$appPayment['mch_id']]['mp_app_id'] = $appPayment['pay_app_id'];
                    break;
                case 'mini':
                    $configs[$appPayment['mch_id']]['mini_app_id'] = $appPayment['pay_app_id'];
                    break;
            }
        }

        return ['wechat' => $configs];
    }

    private static function tidyWechatConfig($paymentInfo, $orderNo = ''): array
    {
        return [
            'mch_id' => $paymentInfo['mch_id'],
            'mch_secret_key' => $paymentInfo['payment']['api_key'],
            'mch_secret_cert' => str_replace(['-----BEGIN PRIVATE KEY-----', '-----END PRIVATE KEY-----', "\n"], '', $paymentInfo['payment']['private_key']),
            'mch_public_cert_path' => storage_path() . $paymentInfo['payment']['mch_public_cert'],
            'return_url' => $paymentInfo['return_url'] . "/payment/return?order_no=" . $orderNo,
            'notify_url' => $paymentInfo['notify_url'],
            'mp_app_id' => $paymentInfo['pay_type'] == AppPayment::PayTypeH5 ? $paymentInfo['pay_app_id'] : '',
            'mini_app_id' => $paymentInfo['pay_type'] == AppPayment::PayTypeMini ? $paymentInfo['pay_app_id'] : '',
            'app_id' => $paymentInfo['pay_type'] == AppPayment::PayTypeApp ? $paymentInfo['pay_app_id'] : '',
            'pay_channel' => $paymentInfo['pay_channel'],
            'pay_type' => $paymentInfo['pay_type'],
        ];
    }

    /**
     * @throws ApiException
     */
    public static function getWechatConfigByType($payType, $appId, $orderNo = ''): array
    {
        $paymentInfo = AppPayment::query()->with('payment')
            ->where('pay_channel', 'wechat')
            ->where('pay_type', $payType)
            ->where('app_id', $appId)
            ->first();
        if (empty($paymentInfo)) {
            throw new ApiException('微信配置信息获取失败');
        }

        return self::tidyWechatConfig($paymentInfo, $orderNo);
    }

    /**
     * @throws ApiException
     */
    public static function getWechatConfigByPaymentId($paymentId): array
    {
        $paymentInfo = AppPayment::query()->with('payment')
            ->where('id', $paymentId)
            ->first();

        if (empty($paymentInfo)) {
            throw new ApiException('微信配置信息获取失败');
        }

        return self::tidyWechatConfig($paymentInfo);
    }

    private static function tidyAlipayConfig($paymentInfo, $orderNo = ''): array
    {
        return [
            'mch_id' => $paymentInfo['mch_id'],
            'app_id' => $paymentInfo['pay_app_id'],
            'app_secret_cert' => $paymentInfo['payment']['private_key'],
            'app_public_cert_path' => storage_path() . $paymentInfo['payment']['public_key'],
            'alipay_public_cert_path' => storage_path() . $paymentInfo['payment']['mch_public_cert'],
            'alipay_root_cert_path' => storage_path() . $paymentInfo['payment']['mch_root_cert'],
            'return_url' => empty($paymentInfo['return_url']) ? '' : ($paymentInfo['return_url'] . "/payment/return?order_no=" . $orderNo),
            'notify_url' => $paymentInfo['notify_url'],
            'pay_channel' => $paymentInfo['pay_channel'],
            'pay_type' => $paymentInfo['pay_type'],
        ];
    }

    /**
     * @throws ApiException
     */
    public static function getAlipayConfigByType($type, $appId, $orderNo = ''): array
    {
        $paymentInfo = AppPayment::query()->with('payment')
            ->where('pay_channel', 'alipay')
            ->where('pay_type', $type)
            ->where('app_id', $appId)
            ->first();

        if (empty($paymentInfo)) {
            throw new ApiException('支付宝配置信息获取失败');
        }

        return self::tidyAlipayConfig($paymentInfo, $orderNo);
    }

    public static function getAlipayConfigByPaymentId($paymentId): array
    {
        $paymentInfo = AppPayment::query()->with('payment')
            ->where('id', $paymentId)
            ->first();

        if (empty($paymentInfo)) {
            throw new AdminException('支付宝配置信息获取失败');
        }

        return self::tidyAlipayConfig($paymentInfo);
    }

    public static function tidyForPayConfig(string $type, array $config)
    {
        $payConfig = config("pay");
        switch ($type) {
            case "alipay":
                $payConfig['alipay']['default'] = $config;
                break;
            case "wechat":
                $payConfig['wechat']['default'] = $config;
                break;
        }

        return $payConfig;
    }

    /**
     * @throws ApiException
     */
    public static function getAlipayClientByType($appId, $type, $orderNo = ''): \Yansongda\Pay\Provider\Alipay
    {
        $payConfig = config("pay");
        $config = self::getAlipayConfigByType($type, $appId, $orderNo);
        $payConfig['alipay']['default'] = $config;

        // dd($config);

        return Pay::alipay($payConfig);
    }

    /**
     * @throws ApiException
     */
    public static function getWechatClientByType($appId, $type, $orderNo = ''): \Yansongda\Pay\Provider\Wechat
    {
        $payConfig = config("pay");
        $config = self::getWechatConfigByType($type, $appId, $orderNo);
        $payConfig['wechat']['default'] = $config;

        return Pay::wechat($payConfig);
    }

    public static function getPayUrl($channel, $payType, $appId)
    {
        return AppPayment::query()
            ->where('pay_channel', $channel)
            ->where('pay_type', $payType)
            ->where('app_id', $appId)
            ->value('return_url');
    }
}
