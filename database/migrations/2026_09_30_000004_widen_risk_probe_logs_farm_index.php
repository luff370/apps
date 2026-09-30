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
        if (!$this->indexExists(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(
                    ['status', 'created_at', 'client_ip', 'device_identity', 'risk_score', 'id'],
                    self::INDEX
                );
            });
        }

        if ($this->indexExists('rpl_status_created_ip_identity')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('rpl_status_created_ip_identity');
            });
        }
    }

    public function down(): void
    {
        if (!$this->indexExists('rpl_status_created_ip_identity')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['status', 'created_at', 'client_ip', 'device_identity'], 'rpl_status_created_ip_identity');
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
