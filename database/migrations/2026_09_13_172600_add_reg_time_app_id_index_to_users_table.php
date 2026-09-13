<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('users', 'users_reg_time_app_id_index')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->index(['reg_time', 'app_id'], 'users_reg_time_app_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_reg_time_app_id_index');
        });
    }
};
