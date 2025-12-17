<?php

namespace App\Support\Utils;

use App\Support\Services\HttpService;

class BaiduAI
{
    /**
     * @throws \Exception
     */
    public static function getAccessToken()
    {
        $cacheKey = 'baidu-ai-access-token';
        $token = cache($cacheKey);
        if (empty($token)) {
            $url = 'https://aip.baidubce.com/oauth/2.0/token';
            $appInfo = config('chatai.channels.baidu');
            $appKey = $appInfo['app_key'] ?? '';
            $appSecret = $appInfo['app_secret'] ?? '';
            if (empty($appKey) || empty($appSecret)) {
                throw new \Exception('appKey appSecret 不能为空');
            }

            $postData = http_build_query(['client_id' => $appKey, 'client_secret' => $appSecret, 'grant_type' => 'client_credentials']);
            $jsonStr = HttpService::postRequest($url, $postData);

            $tokenData = json_decode($jsonStr, true);
            logger()->info('---tokenData---', $tokenData);
            $token = $tokenData['access_token'];
            $ttl = $tokenData['expires_in'];

            cache()->put($cacheKey, $token, $ttl);
        }

        return $token;
    }

    /**
     * @throws \Exception
     */
    public static function run(string $content, array $messages = [])
    {
        $url = "https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/eb-instant?" . http_build_query(['access_token' => self::getAccessToken()]);

        $messages[] = ['role' => 'user', 'content' => $content];
        $postData = json_encode(['messages' => $messages]);
        // logger()->info('----messages----', $messages);

        $response = HttpService::postJson($url, $postData);

        $response =  json_decode($response, true);
        if (!empty($response['error_code'])){
            logger()->error('baiduAI调用错误：',$response);
            throw new \Exception('AI接口服务繁忙');
        }

        return $response;
    }
}

