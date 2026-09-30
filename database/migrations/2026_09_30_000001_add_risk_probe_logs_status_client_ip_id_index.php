<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'risk_probe_logs';

    private const INDEX = 'rpl_status_client_ip_id';

    public function up(): void
    {
        if ($this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->index(['status', 'client_ip', 'id'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (!$this->indexExists(self::TABLE, self::INDEX)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [$table, $index]
        ));
    }
};
