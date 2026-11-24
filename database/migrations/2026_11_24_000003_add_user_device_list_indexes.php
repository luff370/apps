<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEXES = [
        'user_uuids' => ['user_uuids_first_seen_index', ['created_at', 'id']],
        'user_access_log' => ['user_access_log_app_uuid_last_seen_index', ['app_id', 'uuid', 'created_at', 'id']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => [$index, $columns]) {
            if (Schema::hasIndex($table, $index)) {
                continue;
            }

            if (DB::connection()->getDriverName() === 'mysql') {
                $keys = implode(', ', array_map(fn($column) => "`$column`", $columns));
                DB::statement("ALTER TABLE `$table` ADD INDEX `$index` ($keys), ALGORITHM=INPLACE, LOCK=NONE");
            } else {
                Schema::table($table, fn(Blueprint $blueprint) => $blueprint->index($columns, $index));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => [$index]) {
            if (Schema::hasIndex($table, $index)) {
                Schema::table($table, fn(Blueprint $blueprint) => $blueprint->dropIndex($index));
            }
        }
    }
};
