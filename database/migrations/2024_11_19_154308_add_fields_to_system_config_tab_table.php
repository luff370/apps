<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_config_tab', function (Blueprint $table) {
            $table->unsignedInteger('app_id')->default(0)->after('pid')->comment('所属应用');
            $table->unique(['app_id', 'eng_title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_config_tab', function (Blueprint $table) {
            $table->dropColumn('app_id');
            $table->dropUnique(['app_id', 'eng_title']);
        });
    }
};
