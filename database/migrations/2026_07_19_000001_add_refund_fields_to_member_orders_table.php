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
            $table->unsignedTinyInteger('refund_status')->default(0)->comment('退款状态：0未退款 2已退款')->after('pay_price');
            $table->decimal('refund_price', 10, 2)->default(0)->comment('退款金额')->after('refund_status');
            $table->unsignedInteger('refund_time')->default(0)->comment('退款时间')->after('refund_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_orders', function (Blueprint $table) {
            $table->dropColumn(['refund_status', 'refund_price', 'refund_time']);
        });
    }
};
