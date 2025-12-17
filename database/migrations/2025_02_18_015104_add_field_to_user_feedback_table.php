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
        Schema::table('user_feedback', function (Blueprint $table) {
            $table->string('market_channel', 32)->default('')->comment('市场渠道')->after('user_id');
            $table->string('version', 32)->default('')->comment('应用版本号')->after('market_channel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_feedback', function (Blueprint $table) {
            $table->dropColumn('market_channel');
            $table->dropColumn('version');
        });
    }
};
