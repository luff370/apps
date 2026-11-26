<?php

namespace App\Services\Statistics;

use App\Models\SystemApp;
use App\Models\UmengUserUuid;
use Illuminate\Support\Facades\DB;

class UmengDeviceService
{
    public function record(int $appId, string $uuid, string $channel, string $version, string $oaid): void
    {
        $channel = strtolower(trim($channel));
        $channel = SystemApp::marketChannelAliases($channel)[0] ?? $channel;
        $now = now();
        $key = ['app_id' => $appId, 'uuid' => $uuid];

        DB::transaction(function () use ($key, $channel, $version, $oaid, $now) {
            $inserted = UmengUserUuid::query()->insertOrIgnore($key + [
                'market_channel' => $channel,
                'version' => $version,
                'oaid' => $oaid,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if (!$inserted) {
                UmengUserUuid::query()->where($key)->where('updated_at', '<=', $now)->update([
                    'oaid' => $oaid,
                    'updated_at' => $now,
                ]);
            }

            // 空渠道行用于应用合计。同一设备跨渠道访问时，应用合计当天只计一次。
            $activity = $key + ['date' => $now->toDateString()];
            $rows = [$activity + ['market_channel' => '']];
            if ($channel !== '') {
                $rows[] = $activity + ['market_channel' => $channel];
            }
            DB::table('umeng_user_activity')->insertOrIgnore($rows);
        }, 3);
    }
}
