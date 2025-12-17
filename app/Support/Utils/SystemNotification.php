<?php

namespace App\Support\Utils;

use App\Models\Supplier\Supplier;
use App\Models\System\SystemNotice;
use App\Exceptions\NotificationException;

class SystemNotification
{
    public static function send($mark, $content)
    {
        try {
            $tpl = self::getTplByMark($mark);
            if (empty($tpl)) {
                throw new NotificationException("{$mark} 模板获取失败");
            }

            if ($tpl['is_fly_book'] == 1) {
                self::sendToFlyBook($tpl, $content);
            }

            if ($tpl['is_wechat_group'] == 1) {
                self::sendToWechatGroup($tpl, $content);
            }
        } catch (\Exception $exception) {
            logger('系统消息发送失败:' . $exception->getMessage());
        }
    }

    /**
     * 发送飞书群消息
     *
     * @param $tpl
     * @param $data
     */
    public static function sendToFlyBook($tpl, $data)
    {
        $url = $tpl['fly_book_url'];
        $textTpl = $tpl['fly_book_text'];
        if (empty($url) || empty($textTpl)) {
            return;
        }

        $text = Str::keywordsReplace($textTpl, $data);

        FlyBookNotification::send($url, $text);
    }

    /**
     * 发送微信群消息
     *
     * @param $tpl
     * @param $data
     * @param string $title
     */
    public static function sendToWechatGroup($tpl, $data, $title = '')
    {
        $textTpl = $tpl['wechat_group_text'];
        if (empty($textTpl)) {
            return;
        }

        $text = Str::keywordsReplace($textTpl, $data);

        $wechatGroupName = '';
        if (!empty($data['supplier_id'])) {
            $wechatGroupName = Supplier::query()->where('id', $data['supplier_id'])->pluck('wechat_group_name')->first();
        }

        if (empty($wechatGroupName)) {
            return;
        }

        SystemNotice::query()->create([
            'title' => $title,
            'type' => 2,
            'content' => $text,
            'template' => $tpl['mark'],
            'wechat_group_name' => $wechatGroupName,
        ]);
    }

    /**
     * 获取通知模板
     *
     * @throws \Exception
     */
    public static function getTplByMark($mark)
    {
        $cacheKey = 'system_notification:' . $mark;

        $data = json_decode(cache($cacheKey), true);
        if (empty($data)) {
            $data = \App\Models\System\SystemNotification::query()->where('mark', $mark)->first();
            if ($data) {
                $data = $data->toArray();
                cache()->put($cacheKey, json_encode($data), 60);
            }
        }

        return $data;
    }
}
