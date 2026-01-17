<?php

namespace App\Support\Services\ReadTask;

use App\Support\Services\ReadTask\Contracts\ReadTaskChannelInterface;
use Exception;

class ChannelFactory
{
    /**
     * @throws Exception
     */
    public static function make(string $ch): ReadTaskChannelInterface
    {
        return match ($ch) {
            'uu03qqd' => app(Uu03Channel::class),
            'jan_yue' => app(JanYueChannel::class),
            default   => throw new Exception('未知渠道'),
        };
    }
}
