<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('order_no', 32)->comment('订单号');
            $table->string('order_title', 32)->default('')->comment('转账标题');
            $table->decimal('amount')->default(0)->comment('转账金额');
            $table->string('payment_channel', 32)->default('')->comment('支付通道');
            $table->string('product_code', 32)->default('')->comment('转账方式(转账产品编码)');
            $table->string('payee_account_type', 32)->default('')->comment('账户类型(identity_type)');
            $table->string('payee_account', 32)->default('')->comment('收款账户');
            $table->string('payee_name', 32)->default('')->comment('收款人名称');
            $table->dateTime('trans_date')->nullable()->comment('转账时间');
            $table->string('trade_no', 32)->default('')->comment('转账订单号');
            $table->string('settle_serial_no', 64)->default('')->comment('清算机构流水号');
            $table->unsignedTinyInteger('status')->default(0)->comment('转账状态(0-待处理，1-成功，2-失败)');
            $table->string('error_code', 32)->default('')->comment('错误码');
            $table->string('error_msg', 64)->default('')->comment('错误描述');
            $table->string('operator', 32)->default('')->comment('操作人');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_orders');
    }
};
