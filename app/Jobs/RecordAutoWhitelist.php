<?php

namespace App\Jobs;

use App\Services\User\UserWhitelistService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * 自动写入 IP/设备白名单。
 *
 * 响应里只需要白名单开关对应的字段，落库放到响应后再做。
 */
class RecordAutoWhitelist
{
    use Dispatchable;

    public function __construct(
        public $ip,
        public $type,
        public $appId,
        public $marketChannel,
        public $version,
        public $device,
    ) {
    }

    public function handle(): void
    {
        try {
            UserWhitelistService::createByIp(
                $this->ip,
                $this->type,
                '',
                3,
                $this->appId,
                $this->marketChannel,
                $this->version,
                $this->device
            );
            UserWhitelistService::createByDevice(
                $this->device,
                $this->type,
                '',
                3,
                $this->appId,
                $this->marketChannel,
                $this->ip,
                $this->version
            );
        } catch (\Throwable $exception) {
            logger()->error('自动添加白名单失败：' . $exception->getMessage(), [
                'app_id' => $this->appId,
                'ip' => $this->ip,
                'device' => $this->device,
            ]);
        }
    }
}
