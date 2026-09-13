<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncHistoricalUserUuids extends Command
{
    protected $signature = 'app:sync-historical-user-uuids';

    protected $description = '从 user_access_log 回填 user_uuids 历史首次出现时间';

    public function handle(): int
    {
        $before = (int) DB::table('user_uuids')->count();
        $this->info("开始回填，当前 user_uuids：{$before}");

        DB::statement("
            INSERT INTO user_uuids (app_id, uuid, market_channel, created_at, updated_at)
            SELECT
                app_id,
                uuid,
                MIN(IFNULL(market_channel, '')),
                MIN(created_at),
                NOW()
            FROM user_access_log
            WHERE uuid <> ''
              AND app_id > 0
            GROUP BY app_id, uuid
            ON DUPLICATE KEY UPDATE
                created_at = LEAST(created_at, VALUES(created_at)),
                updated_at = NOW()
        ");

        $after = (int) DB::table('user_uuids')->count();
        $this->info("完成，行数 {$before} -> {$after}，新增 " . ($after - $before) . " 条");

        return self::SUCCESS;
    }
}
