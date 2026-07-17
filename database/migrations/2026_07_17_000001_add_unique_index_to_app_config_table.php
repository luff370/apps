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
        Schema::table('app_config', function (Blueprint $table) {
            $table->unique(
                ['app_id', 'version', 'channel', 'key'],
                'app_config_app_version_channel_key_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_config', function (Blueprint $table) {
            $table->dropUnique('app_config_app_version_channel_key_unique');
        });
    }
};
