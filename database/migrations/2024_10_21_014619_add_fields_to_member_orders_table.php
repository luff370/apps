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
            $table->string('pay_status', 32)->default('unpaid')->comment('支付状态')->after('pay_source');
            $table->string('member_status', 32)->default('not_ordered')->comment('会员状态')->after('pay_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_orders', function (Blueprint $table) {
            $table->dropColumn('pay_status');
            $table->dropColumn('member_status');
        });
    }
};
