<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->dropUnique('user_profile_uuid_unique');
            $table->unique(['uuid', 'app_id'], 'user_profile_uuid_app_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_profile', function (Blueprint $table) {
            $table->dropUnique('user_profile_uuid_app_id_unique');
            $table->unique('uuid', 'user_profile_uuid_unique');
        });
    }
};
