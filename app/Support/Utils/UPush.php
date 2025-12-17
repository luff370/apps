<?php

namespace App\Support\Utils;

use Hedeqiang\UMeng\Android;
use Hedeqiang\UMeng\Facades\Push;
use Hedeqiang\UMeng\IOS;

class UPush
{
    public static array $instance = [];

    public static function getInstance($appId, $platform)
    {
        $key = $appId . '-' . $platform;
        if (isset(self::$instance[$key])) {
            return self::$instance[$key];
        }

        $config = [
            'Android' => [
                'appKey' => sys_config('uPush_app_key', $appId),
                'appMasterSecret' => sys_config('uPush_app_secret', $appId),
                'production_mode' => true,
            ],
            'iOS' => [
                'appKey' => sys_config('uPush_app_key', $appId),
                'appMasterSecret' => sys_config('uPush_app_secret', $appId),
                'production_mode' => true,
            ]
        ];
        logger()->info('uPush推送配置--' . json_encode($config));

        switch ($platform) {
            case 'ios':
                $ios = new IOS($config);
                self::$instance[$key] = $ios;
                return $ios;
            case 'android':
                $android = new Android($config);
                self::$instance[$key] = $android;
                return $android;
        }

        return null;
    }

    /**
     * @throws \Exception
     */
    public static function Send($appId, $deviceToken, $title, $content, $platform): array
    {
        $app = self::getInstance($appId, $platform);
        if (empty($app)) {
            throw new \Exception("应用{$appId}, {$platform} 获取推送对象失败");
        }

        switch (strtolower($platform)) {
            case 'android':
                $params = [
                    'type' => 'unicast',
                    'device_tokens' => $deviceToken,
                    'payload' => [
                        'display_type' => 'notification',
                        'body' => [
                            'title' => $title,
                            'text' => $content,
                            'badge' => 1,
                        ],
                    ],
                    'policy' => [
                        'expire_time' => now()->addDay()->toDateTimeString(),
                    ],
                    //'description' => '测试单播消息-Android',
                ];


                // $res = Push::android()->send($params);
                break;
            case 'ios':
                $params = [
                    'type' => 'unicast',
                    'device_tokens' => $deviceToken,
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                // 'subtitle' => '',
                                'body' => $content,
                                'badge' => 1,
                            ]
                        ],
                    ],
                    'policy' => [
                        'expire_time' => now()->addDay()->toDateTimeString(),
                    ],
                    //'description' => '测试单播消息-iOS',
                ];

                // $res = Push::ios()->send($params);
                break;
            default:
                throw new \Exception("the {$platform} not supported");
        }

        return $app->send($params);
    }

}
