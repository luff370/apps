<?php

namespace App\Support\Services\ReadTask;

class JanYueChannel extends AbstractReadTaskChannel
{

    protected string $ch = '6994';
    protected string $getReadUrl = 'https://xxx.com/api';

    protected function processCallback(array $data): void
    {
        // 各自解析字段
    }


}
