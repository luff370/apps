<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'risk_probe_logs';

    private const INDEX = 'rpl_farm_ip_agg';

    public function up(): void
    {
        if ($this->indexExists(self::INDEX) && !$this->indexHasColumn(self::INDEX, 'compliance_mode')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }

        if (!$this->indexExists(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(
                    ['status', 'created_at', 'client_ip', 'device_identity', 'risk_score', 'id', 'compliance_mode'],
                    self::INDEX
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }

        if (!$this->indexExists(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(
                    ['status', 'created_at', 'client_ip', 'device_identity', 'risk_score', 'id'],
                    self::INDEX
                );
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

    private function indexHasColumn(string $index, string $column): bool
    {
        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? and column_name = ? limit 1',
            [self::TABLE, $index, $column]
        ));
    }
};
