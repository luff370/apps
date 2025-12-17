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
        Schema::table('member_orders', function (Blueprint $table) {
            $table->string('market_channel', 32)->nullable()->default('')->comment('应用渠道')->after('remark');
            $table->string('version', 32)->nullable()->default('')->comment('应用版本')->after('market_channel');

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_orders', function (Blueprint $table) {
            $table->dropColumn('market_channel');
            $table->dropColumn('version');

            $table->dropIndex('member_orders_user_id_index');
            $table->dropIndex('member_orders_created_at_index');
        });
    }
};
