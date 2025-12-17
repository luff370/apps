<?php

namespace App\Support\Utils;

class Bilibili
{
    public static function getBilibiliVideoInfo($bv)
    {
        $apiUrl = "https://api.bilibili.com/x/web-interface/view?bvid=" . urlencode($bv);

        // 使用 file_get_contents 获取数据
        $jsonData = file_get_contents($apiUrl);

        if ($jsonData === false) {
            return [];
        }

        // 解码 JSON 数据
        $data = json_decode($jsonData, true);

        if ($data['code'] != 0) {
            // 请求失败，返回错误信息
            logger()->error($data['message']);
        }

        return $data['data'] ?? [];
    }


}
