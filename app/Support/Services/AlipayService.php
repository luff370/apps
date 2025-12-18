<?php

namespace App\Support\Services;

class AlipayService
{
    protected $appId;
    protected $charset = 'utf-8';
    protected $rsaPrivateKey;
    protected $alipayPublicKey;
    protected $gateway = "https://openapi.alipay.com/gateway.do";

    public static function instance(array $config = []): AlipayService
    {
        return new static($config);
    }

    public function __construct($config)
    {
        $this->setAppId($config['app_id']);
        $this->setRsaPrivateKey($config['merchant_private_key']);
        $this->setAlipayPublicKey($config['alipay_public_key']);
    }

    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    public function setRsaPrivateKey($key)
    {
        $this->rsaPrivateKey = $key;
    }

    public function setAlipayPublicKey($key)
    {
        $this->alipayPublicKey = $key;
    }

    /*----------------------------------------
     |  核心签名方法（你已有）
     ----------------------------------------*/
    public function generateSign($params, $signType = "RSA2"): string
    {
        return $this->sign($this->getSignContent($params), $signType);
    }

    protected function sign($data, $signType = "RSA2"): string
    {
        $priKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($this->rsaPrivateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        $algo = OPENSSL_ALGO_SHA256;
        openssl_sign($data, $sign, $priKey, $algo);
        return base64_encode($sign);
    }

    /*----------------------------------------
     |  新增：验签（支付宝返回数据使用）
     ----------------------------------------*/
    public function verifySign($data, $sign): bool
    {
        $public = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->alipayPublicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        return openssl_verify($data, base64_decode($sign), $public, OPENSSL_ALGO_SHA256) === 1;
    }

    /*----------------------------------------
     |  新增接口：alipay.system.oauth.token
     |  作用：通过 auth_code 获取 access_token + user_id
     ----------------------------------------*/
    /**
     * @throws \Exception
     */
    public function systemOauthToken($code)
    {
        $params = [
            'app_id'     => $this->appId,
            'method'     => 'alipay.system.oauth.token',
            'format'     => 'JSON',
            'charset'    => $this->charset,
            'sign_type'  => 'RSA2',
            'timestamp'  => date('Y-m-d H:i:s'),
            'version'    => '1.0',
            'grant_type' => 'authorization_code',
            'code'       => $code,
        ];

        $params['sign'] = $this->generateSign($params);

        $result = $this->curlPost($this->gateway . "?charset=" . $this->charset, $params);
        $data =  json_decode($result, true);

        if (empty($data['alipay_system_oauth_token_response'])){
            logger()->error('获取access_token 失败', $data);
            throw new \Exception('获取access_token 错误'.($data['error_response']['sub_msg'] ?? ''));
        }

        return $data['alipay_system_oauth_token_response'];
    }

    /*----------------------------------------
     |  新增接口：alipay.user.info.share
     |  获取用户头像、昵称（需要已授权 scope）
     ----------------------------------------*/
    /**
     * @throws \Exception
     */
    public function getUserInfo($accessToken)
    {
        $params = [
            'app_id'     => $this->appId,
            'method'     => 'alipay.user.info.share',
            'format'     => 'JSON',
            'charset'    => $this->charset,
            'sign_type'  => 'RSA2',
            'timestamp'  => date('Y-m-d H:i:s'),
            'version'    => '1.0',
            'auth_token' => $accessToken,
        ];

        $params['sign'] = $this->generateSign($params);

        $result = $this->curlPost($this->gateway . "?charset=" . $this->charset, $params);
        $data = json_decode($result, true);

        if (empty($data['alipay_user_info_share_response'])){
            logger()->error('获取alipay_user_info 失败', $data);
            throw new \Exception('获取alipay_user_info 错误'.($data['error_response']['sub_msg'] ?? ''));
        }

        return $data['alipay_user_info_share_response'];
    }


    /*----------------------------------------
     |  下面是你已有的辅助方法，原样保留
     ----------------------------------------*/
    protected function checkEmpty($value): bool
    {
        if (!isset($value)) return true;
        if ($value === null) return true;
        if (trim($value) === "") return true;
        return false;
    }

    public function getSignContent($params)
    {
        ksort($params);
        $string = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (!$this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                $v = $this->characet($v, $this->charset);
                $string .= ($i == 0 ? "" : "&") . "$k=$v";
                $i++;
            }
        }
        return $string;
    }

    function characet($data, $targetCharset)
    {
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
            }
        }
        return $data;
    }

    public function curlPost($url = '', $postData = '', $options = [])
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}
