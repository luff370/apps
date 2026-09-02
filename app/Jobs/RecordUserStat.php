<?php

namespace App\Jobs;

use App\Services\Statistics\UserStatisticsService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * 用户新增、活跃统计。
 *
 * 通过 dispatchAfterResponse 在响应发出后再跑，避免拖慢主接口。
 */
class RecordUserStat
{
    use Dispatchable;

    public function __construct(
        public $uuid,
        public $appId,
        public $marketChannel = '',
    ) {
    }

    public function handle(UserStatisticsService $service): void
    {
        if (empty($this->uuid) || empty($this->appId)) {
            return;
        }

        try {
            $service->recordAppInfoVisit($this->uuid, $this->appId, $this->marketChannel);
        } catch (\Throwable $exception) {
            logger()->error('用户统计失败：' . $exception->getMessage(), [
                'uuid' => $this->uuid,
                'app_id' => $this->appId,
                'market_channel' => $this->marketChannel,
            ]);
        }
    }
}
