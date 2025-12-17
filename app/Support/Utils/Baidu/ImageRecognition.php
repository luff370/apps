<?php

namespace App\Support\Utils\Baidu;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImageRecognition
{
    protected static array $endpoints = [
        'animal'     => 'https://aip.baidubce.com/rest/2.0/image-classify/v1/animal',
        'plant'      => 'https://aip.baidubce.com/rest/2.0/image-classify/v1/plant',
        'logo'       => 'https://aip.baidubce.com/rest/2.0/image-classify/v2/logo',
        'car'        => 'https://aip.baidubce.com/rest/2.0/image-classify/v1/car',
        'dish'       => 'https://aip.baidubce.com/rest/2.0/image-classify/v2/dish',
        'object'     => 'https://aip.baidubce.com/rest/2.0/image-classify/v2/advanced_general',
        'ingredient' => 'https://aip.baidubce.com/rest/2.0/image-classify/v1/classify_ingredient',
    ];

    public static function recognize(string $type, string $imagePathOrUrl, array $options = []): array
    {
        if (!isset(self::$endpoints[$type])) {
            throw new \InvalidArgumentException("Unsupported recognition type: $type");
        }

        $accessToken = self::getAccessToken();
        $url = self::$endpoints[$type] . '?access_token=' . $accessToken;

        $postData = $options;

        if (Str::startsWith($imagePathOrUrl, ['http://', 'https://'])) {
            $postData['url'] = $imagePathOrUrl;
        } else {
            $postData['image'] = base64_encode(file_get_contents($imagePathOrUrl));
        }

        $response = Http::asForm()->post($url, $postData);

        return $response->json();
    }

    public static function getSupportedTypes(): array
    {
        return array_keys(self::$endpoints);
    }

    protected static function getAccessToken(): string
    {
        return Cache::remember('baidu_access_token', 60 * 29, function () {
            $appInfo = config('chatai.channels.baidu');
            $apiKey = $appInfo['app_key'] ?? '';
            $secretKey = $appInfo['app_secret'] ?? '';
            if (empty($apiKey) || empty($secretKey)) {
                throw new \Exception('appKey appSecret 不能为空');
            }

            $response = Http::asForm()->get('https://aip.baidubce.com/oauth/2.0/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $apiKey,
                'client_secret' => $secretKey,
            ]);

            return $response->json()['access_token'] ?? '';
        });
    }
}
