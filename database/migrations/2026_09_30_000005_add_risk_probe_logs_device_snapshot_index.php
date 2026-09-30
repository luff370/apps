<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'risk_probe_logs';

    private const INDEX = 'rpl_device_snapshot';

    public function up(): void
    {
        if (!$this->indexExists(self::INDEX)) {
            DB::statement(
                'ALTER TABLE `' . self::TABLE . '` ADD INDEX `' . self::INDEX . '` (`status`, `device_identity`, `id` DESC, `risk_score`, `compliance_mode`, `ad_switch`)'
            );
        }

        // (status, device_identity, id) 已被上面的索引覆盖，避免每次写入维护两份。
        if ($this->indexExists('rpl_status_identity_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('rpl_status_identity_id');
            });
        }
    }

    public function down(): void
    {
        if (!$this->indexExists('rpl_status_identity_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['status', 'device_identity', 'id'], 'rpl_status_identity_id');
            });
        }

        if ($this->indexExists(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }

    private function indexExists(string $index): bool
    {
        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [self::TABLE, $index]
        ));
    }
};
