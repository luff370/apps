<?php

namespace App\Jobs;

use App\Services\Statistics\UserStatisticsService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * app_info 用户统计。
 *
 * 通过 dispatchAfterResponse 在响应发出后再跑，避免拖慢主接口。
 */
class RecordAppInfoUserStat
{
    use Dispatchable;

    public function __construct(
        public string $uuid,
        public int $appId,
        public string $marketChannel = '',
    ) {
    }

    public function handle(UserStatisticsService $service): void
    {
        if ($this->uuid === '' || $this->appId <= 0) {
            return;
        }

        try {
            $service->recordAppInfoVisit($this->uuid, $this->appId, $this->marketChannel);
        } catch (\Throwable $exception) {
            logger()->error('app_info 用户统计失败：' . $exception->getMessage(), [
                'uuid' => $this->uuid,
                'app_id' => $this->appId,
                'market_channel' => $this->marketChannel,
            ]);
        }
    }
}
