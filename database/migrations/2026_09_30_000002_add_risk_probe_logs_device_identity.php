<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'risk_probe_logs';

    public function up(): void
    {
        if (!$this->columnExists('device_identity')) {
            DB::statement(
                "ALTER TABLE `" . self::TABLE . "` ADD COLUMN `device_identity` VARCHAR(255) GENERATED ALWAYS AS (COALESCE(NULLIF(`user_uuid`, ''), NULLIF(`device_sn`, ''))) VIRTUAL"
            );
        }

        if (!$this->indexExists('rpl_status_identity_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['status', 'device_identity', 'id'], 'rpl_status_identity_id');
            });
        }

        if (!$this->indexExists('rpl_status_created_at')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['status', 'created_at'], 'rpl_status_created_at');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('rpl_status_created_at')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('rpl_status_created_at');
            });
        }
        if ($this->indexExists('rpl_status_identity_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('rpl_status_identity_id');
            });
        }
        if ($this->columnExists('device_identity')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropColumn('device_identity');
            });
        }
    }

    private function columnExists(string $column): bool
    {
        return !empty(DB::select(
            'select 1 from information_schema.columns where table_schema = database() and table_name = ? and column_name = ? limit 1',
            [self::TABLE, $column]
        ));
    }

    private function indexExists(string $index): bool
    {
        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [self::TABLE, $index]
        ));
    }
};
