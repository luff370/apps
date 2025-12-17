<?php

namespace App\Support\Utils;

class Str
{
    /**
     * 匹配手机号
     *
     * @param string $str
     *
     * @return string
     */
    public static function matchPhone(string $str): string
    {
        preg_match('/(\d{11})/', $str, $matches); // 使用正则表达式进行匹配
        if (isset($matches[0])) {
            return $matches[0];
        }

        return '';
    }

    /**
     * 用数组中的值替换文本模板中的关键字
     * @param string $text
     * @param array $data
     *
     * @return string
     */
    public static function keywordsReplace(string $text,array $data):string
    {
        foreach ($data as $key =>$item) {
            $text = str_replace('{' . $key . '}', $item, $text);
        }

        return $text;
    }
}
