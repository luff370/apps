<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'app_config';

    private const OLD_INDEX = 'app_config_app_version_channel_key_unique';

    private const NEW_INDEX = 'app_config_app_channel_key_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->indexExists(self::TABLE, self::OLD_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::OLD_INDEX);
            });
        }

        if (!$this->indexExists(self::TABLE, self::NEW_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['app_id', 'channel', 'key'], self::NEW_INDEX);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists(self::TABLE, self::NEW_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::NEW_INDEX);
            });
        }

        if (!$this->indexExists(self::TABLE, self::OLD_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['app_id', 'version', 'channel', 'key'], self::OLD_INDEX);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return !empty(DB::select(
            'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ? limit 1',
            [$table, $index]
        ));
    }
};
