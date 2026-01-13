<?php

namespace App\Support\Services\ReadTask;

use App\Support\Utils\Http;

class Uu03Channel extends AbstractReadTaskChannel
{
    protected string $ch = 'uu03qqd';
    protected string $getReadUrl = 'http://47.57.244.93/read_channel_api/get_read_url';

    protected function processCallback(array $data): void
    {
        $date = $data['date'] ?? '';
        $readTimes = $data['read_times'] ?? 0;

        logger()->info('uu03 callback', [
            'date'       => $date,
            'read_times' => $readTimes,
        ]);

        // 👉 这里写你自己的业务逻辑
    }
}
