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
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->string('agreement_no', 128)->default('')->comment('支付宝签约协议号')->after('original_transaction_id');
            $table->string('external_agreement_no', 128)->default('')->comment('商户签约协议号')->after('agreement_no');
            $table->string('agreement_status', 32)->default('')->comment('支付宝签约状态')->after('status');
            $table->dateTime('next_pay_date')->nullable()->comment('下次自动扣款时间')->after('renewal_date');
            $table->dateTime('last_pay_date')->nullable()->comment('上次自动扣款时间')->after('purchase_date');
            $table->unsignedTinyInteger('deduct_fail_count')->default(0)->comment('连续扣款失败次数')->after('subscribe_fail_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropColumn('agreement_no');
            $table->dropColumn('external_agreement_no');
            $table->dropColumn('agreement_status');
            $table->dropColumn('next_pay_date');
            $table->dropColumn('last_pay_date');
            $table->dropColumn('deduct_fail_count');
        });
    }
};
