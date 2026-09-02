<?php

namespace App\Jobs;

use App\Services\User\UserAccessLogService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * 访问日志。
 *
 * 通过 dispatchAfterResponse 在响应发出后再跑，避免拖慢主接口。
 */
class RecordAccessLog
{
    use Dispatchable;

    public function __construct(
        public $uid,
        public $appId,
        public $marketChannel,
        public $version,
        public $os,
        public $uuid,
        public $device,
        public $ip,
        public $source,
        public $returnData = [],
    ) {
    }

    public function handle(): void
    {
        if (empty($this->uuid)) {
            return;
        }

        try {
            UserAccessLogService::record(
                $this->uid,
                $this->appId,
                $this->marketChannel,
                $this->version,
                $this->os,
                $this->uuid,
                $this->device,
                $this->ip,
                $this->source,
                $this->returnData,
            );
        } catch (\Throwable $exception) {
            logger()->error('访问日志失败：' . $exception->getMessage(), [
                'uuid' => $this->uuid,
                'app_id' => $this->appId,
                'market_channel' => $this->marketChannel,
            ]);
        }
    }
}
