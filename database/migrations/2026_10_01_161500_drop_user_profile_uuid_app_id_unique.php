<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->dropUnique('user_profile_uuid_app_id_unique');
            $table->index(['app_id', 'uuid'], 'user_profile_app_id_uuid_index');
            $table->index(['app_id', 'user_id'], 'user_profile_app_id_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->dropIndex('user_profile_app_id_uuid_index');
            $table->dropIndex('user_profile_app_id_user_id_index');
            $table->unique(['uuid', 'app_id'], 'user_profile_uuid_app_id_unique');
        });
    }
};
