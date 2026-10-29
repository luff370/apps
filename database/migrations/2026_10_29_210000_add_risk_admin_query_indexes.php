<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEXES = [
        'risk_probe_logs' => [
            // 应用与状态等值后按设备分组；尾部列覆盖快照回读。
            'rpl_app_device_snapshot' => '`app_id`, `status`, `device_identity`, `id`, `risk_score`, `compliance_mode`, `ad_switch`',
            'rpl_app_time_agg' => '`app_id`, `status`, `created_at`, `client_ip`, `device_identity`, `risk_score`, `compliance_mode`',
        ],
        'users' => [
            'users_app_uuid_risk' => '`app_id`, `uuid`, `is_del`',
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! $this->exists($table, $name)) {
                    // 不复制大表；若数据库不支持在线加索引则明确失败，避免静默长时间锁表。
                    DB::statement("ALTER TABLE `$table` ADD INDEX `$name` ($columns), ALGORITHM=INPLACE, LOCK=NONE");
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if ($this->exists($table, $name)) {
                    DB::statement("ALTER TABLE `$table` DROP INDEX `$name`, ALGORITHM=INPLACE, LOCK=NONE");
                }
            }
        }
    }

    private function exists(string $table, string $name): bool
    {
        return ! empty(DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $name]
        ));
    }
};
