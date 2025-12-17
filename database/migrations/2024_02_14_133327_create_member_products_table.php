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
        Schema::create('member_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('name',32)->comment('产品名称');
            $table->string('label',32)->default('')->comment('标签');
            $table->string('keyword',32)->default('')->comment('关键字');
            $table->decimal('ot_price')->comment('原价');
            $table->decimal('price')->comment('售价');
            $table->string('validity_type',32)->comment('会员时效类型(year,month,day,hour,times)');
            $table->string('give_type',32)->comment('会员赠送时效类型(year,month,day,hour,times)');
            $table->unsignedInteger('validity')->comment('会员时效值');
            $table->unsignedInteger('give_validity')->comment('赠送会员时效值');
            $table->string('pay_product_id', 32)->default('')->comment('支付产品ID');
            $table->string('filter_code', 32)->default('')->comment('过滤码');
            $table->string('platform', 32)->default('')->comment('应用平台（ios android）');
            $table->string('serial_number', 64)->default('')->comment('序列号');
            $table->unsignedTinyInteger('is_subscribe')->default(0)->comment('是否自动订阅');
            $table->string('pay_cycle',32)->comment('扣款周期类型(year,month,day)');
            $table->unsignedInteger('pay_cycle_val')->default(0)->comment('扣款周期');
            $table->string('grace_period_type')->default('')->comment('宽限周期类型(month,day)');
            $table->unsignedInteger('grace_period')->default(0)->comment('宽限周期');
            $table->decimal('renewal_price')->default(0)->comment('续订金额');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('是否启用');
            $table->string('remark')->default('')->comment('备注');

            $table->unsignedInteger('create_time')->comment('创建时间');
            $table->unsignedInteger('update_time')->default(0)->comment('修改时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_products');
    }
};
