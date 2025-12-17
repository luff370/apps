<?php

namespace App\Services\Alipay;

use Alipay\EasySDK\Kernel\Factory;
use Alipay\EasySDK\Kernel\Config;
use App\Services\Service;
use Exception;

class AuthService extends Service
{
    public function __construct()
    {
        $this->initAlipaySDK();
    }

    private function initAlipaySDK(): void
    {
        // 从Laravel配置文件读取支付宝配置
        $alipayConfig = config('alipay');

        // 创建SDK配置对象
        $sdkConfig = new Config();
        $sdkConfig->appId = $alipayConfig['app_id'];
        $sdkConfig->merchantPrivateKey = $alipayConfig['merchant_private_key'];
        $sdkConfig->merchantCertPath = $alipayConfig['merchant_cert_path'];
        $sdkConfig->alipayCertPath = $alipayConfig['alipay_cert_path'];
        $sdkConfig->alipayRootCertPath = $alipayConfig['alipay_root_cert_path'];
        $sdkConfig->notifyUrl = $alipayConfig['notify_url'];

        // 关键安全配置（必须为布尔值）
        $sdkConfig->ignoreSSL = (bool) $alipayConfig['ignore_ssl'];

        // 其他必要配置
        $sdkConfig->signType = 'RSA2';
        $sdkConfig->gatewayHost = 'openapi.alipay.com';

        // 全局初始化SDK
        Factory::setOptions($sdkConfig);
    }

    /**
     * 使用 auth_code 换取 access_token
     */
    public function getAccessToken(string $authCode)
    {
        try {
            $result = Factory::base()->oauth()->getToken($authCode);
            logger()->info('Alipay getAccessToken', ['result' => $result]);

            if ($result->code === '10000') {
                return [
                    'access_token' => $result->accessToken,
                    'alipay_user_id' => $result->userId,
                ];
            }

            throw new Exception("Alipay API Error: {$result->msg}");
        } catch (Exception $e) {
            logger()->error('Alipay getAccessToken failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(string $accessToken)
    {
        try {
            $result = Factory::userInfo()->get($accessToken);

            if ($result->code === '10000') {
                return [
                    'user_id' => $result->userId,
                    'nick_name' => $result->nickName,
                    'avatar' => $result->avatar,
                    'gender' => $result->gender,
                    'province' => $result->province,
                    'city' => $result->city,
                ];
            }

            throw new Exception("Alipay API Error: {$result->msg}");
        } catch (Exception $e) {
            logger()->error('Alipay getUserInfo failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}

