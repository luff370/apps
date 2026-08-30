<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_statistics', function (Blueprint $table) {
            $table->dropUnique(['app_id', 'date']);
            $table->string('market_channel', 32)->default('')->comment('应用市场渠道，空表示应用合计')->after('app_id');
            $table->unique(['app_id', 'date', 'market_channel']);
        });
    }

    public function down(): void
    {
        Schema::table('user_statistics', function (Blueprint $table) {
            $table->dropUnique(['app_id', 'date', 'market_channel']);
            $table->dropColumn('market_channel');
            $table->unique(['app_id', 'date']);
        });
    }
};
