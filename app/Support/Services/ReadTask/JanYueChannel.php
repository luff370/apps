<?php

namespace App\Support\Services\ReadTask;

class JanYueChannel extends AbstractReadTaskChannel
{

    protected string $ch = '6994';
    protected string $getReadUrl = 'http://rdapi.hzjianyue.cn/api/getTaskUrl2';

    protected function processCallback(array $data): void
    {
        // 各自解析字段
    }


}
