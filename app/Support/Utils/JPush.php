<?php

namespace App\Support\Utils;

use Exception;
use JPush\Client as Push;

class JPush
{
    public static array $instance = [];

    public static function client($appId): Push
    {
        if (isset(self::$instance[$appId])) {
            return self::$instance[$appId];
        }

        logger()->info('jPush_app_key:' . sys_config('jPush_app_key', $appId));
        logger()->info('jPush_app_secret:' . sys_config('jPush_app_secret', $appId));

        $client = new Push(sys_config('jPush_app_key', $appId), sys_config('jPush_app_secret', $appId));
        self::$instance[$appId] = $client;

        return $client;
    }

    /**
     * @throws Exception
     */
    public static function Send($appId, $deviceToken, $title, $content, $platform = ''): array
    {
        return self::client($appId)->push()
            ->setPlatform(['ios', 'android'])
            ->addAlias($deviceToken)
            ->setNotificationAlert($content)
            ->iosNotification([
                'title' => $title,           // iOS通知标题
                'body' => $content,          // iOS通知内容
            ], [
                'sound' => 'default',
                'badge' => '+1',
            ])
            ->androidNotification($content, [
                'title' => $title,
                'builder_id' => 1,
            ])
            ->send();
    }
}
