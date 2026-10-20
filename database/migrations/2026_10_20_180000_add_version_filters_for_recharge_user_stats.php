<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('user_uuids', 'version')) {
            Schema::table('user_uuids', function (Blueprint $table) {
                $table->string('version', 32)->default('')->comment('首次出现时的应用版本')->after('market_channel');
            });
        }

        if (!Schema::hasColumn('users', 'app_version')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('app_version', 32)->default('')->comment('注册时的应用版本')->after('market_channel');
            });
        }

        if (!Schema::hasIndex('user_access_log', 'user_access_log_app_created_version_uuid_index')) {
            Schema::table('user_access_log', function (Blueprint $table) {
                $table->index(
                    ['app_id', 'created_at', 'version', 'uuid'],
                    'user_access_log_app_created_version_uuid_index'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('user_access_log', 'user_access_log_app_created_version_uuid_index')) {
            Schema::table('user_access_log', function (Blueprint $table) {
                $table->dropIndex('user_access_log_app_created_version_uuid_index');
            });
        }

        if (Schema::hasColumn('users', 'app_version')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('app_version');
            });
        }

        if (Schema::hasColumn('user_uuids', 'version')) {
            Schema::table('user_uuids', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
