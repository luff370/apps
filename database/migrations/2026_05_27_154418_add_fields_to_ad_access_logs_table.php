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
        Schema::table('ad_access_logs', function (Blueprint $table) {
            //
            $table->string("market_channel")->comment('应用市场')->after("app_id");
            $table->string("version")->comment('应用版本')->after("market_channel");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ad_access_logs', function (Blueprint $table) {
            //
            $table->dropColumn("market_channel");
            $table->dropColumn("version");
        });
    }
};
