<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class WhitelistLogFillUserId extends Command
{
    protected $signature = 'app:whitelist-log-fill-user-id
                            {--days=14 : 回补最近多少天，从当天 0 点起}
                            {--since= : 起始时间，优先于 days，例如 2026-10-14 00:00:00}
                            {--dry-run : 只统计可补数量，不更新}
                            {--force : 跳过确认直接更新}';

    protected $description = '按 app_id + uuid 回补白名单日志的 user_id';

    public function handle(): int
    {
        $since = $this->since();
        if ($since === null) {
            return self::FAILURE;
        }

        DB::disableQueryLog();
        $db = DB::connection();
        $database = $db->selectOne('SELECT DATABASE() AS db')->db;
        $this->info("database={$database} since={$since}");

        $pending = $this->countPending($since);
        $matchable = $this->countMatchable($since);
        $emptyUuid = $this->countEmptyUuid($since);

        $this->info("待补 user_id=0 且有 uuid：{$pending}");
        $this->info("能按 app_id + uuid 对上未删除用户：{$matchable}");
        $this->line("同时间范围内 uuid 为空、无法按 uuid 回补：{$emptyUuid}");

        if ($matchable === 0 || $this->option('dry-run')) {
            $this->info($matchable === 0 ? '没有可回补的记录' : 'dry-run，未更新');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm("确认把 {$matchable} 条日志的 user_id 补上？")) {
            $this->warn('已取消');

            return self::SUCCESS;
        }

        $updated = $db->update($this->updateSql(), [$since]);
        $this->info("已更新 {$updated} 条");

        return self::SUCCESS;
    }

    private function since(): ?string
    {
        $since = trim((string) $this->option('since'));
        if ($since !== '') {
            $time = strtotime($since);
            if ($time === false) {
                $this->error('since 不是有效时间');

                return null;
            }

            return date('Y-m-d H:i:s', $time);
        }

        $days = (int) $this->option('days');
        if ($days <= 0) {
            $this->error('days 必须大于 0');

            return null;
        }

        return now()->subDays($days)->startOfDay()->toDateTimeString();
    }

    private function countPending(string $since): int
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) AS c
             FROM user_whitelist_log
             WHERE user_id = 0
               AND uuid IS NOT NULL
               AND uuid <> \'\'
               AND created_at >= ?',
            [$since]
        )->c;
    }

    private function countEmptyUuid(string $since): int
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) AS c
             FROM user_whitelist_log
             WHERE user_id = 0
               AND (uuid IS NULL OR uuid = \'\')
               AND created_at >= ?',
            [$since]
        )->c;
    }

    private function countMatchable(string $since): int
    {
        return (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM (' . $this->matchSql() . ') matched',
            [$since]
        )->c;
    }

    private function updateSql(): string
    {
        return 'UPDATE user_whitelist_log AS l
            INNER JOIN (' . $this->matchSql() . ') AS matched ON matched.log_id = l.id
            SET l.user_id = matched.user_id';
    }

    /**
     * 同一 app_id + uuid 有多条未删除用户时，取 id 最大的一条，和线上写入逻辑一致。
     */
    private function matchSql(): string
    {
        return 'SELECT l2.id AS log_id, MAX(u.id) AS user_id
            FROM user_whitelist_log AS l2
            INNER JOIN users AS u
                ON u.app_id = l2.app_id
               AND u.uuid = l2.uuid
               AND u.is_del = 0
            WHERE l2.user_id = 0
              AND l2.uuid IS NOT NULL
              AND l2.uuid <> \'\'
              AND l2.created_at >= ?
            GROUP BY l2.id';
    }
}
