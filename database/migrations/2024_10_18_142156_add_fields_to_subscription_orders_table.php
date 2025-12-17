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
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->unsignedInteger('app_id')->default(0)->comment('应用ID')->after('id');
            $table->string('pay_type', 32)->comment('支付类型')->after('product_id');
            $table->string('currency', 32)->comment('货币类型')->after('pay_type');
            $table->decimal('pay_amount')->default(0)->comment('支付总金额')->after('currency');
            $table->unsignedTinyInteger('subscribe_success_count')->default(0)->comment('订阅成功次数')->after('status');
            $table->unsignedTinyInteger('subscribe_fail_count')->default(0)->comment('订阅失败次数')->after('subscribe_success_count');
            $table->string('subscribe_fail_reason')->default('')->comment('订阅失败原因')->after('subscribe_fail_count');
            $table->dateTime('grace_period_expires_date')->nullable()->comment('宽限期到期时间')->after('expires_date');
            $table->dateTime('renewal_date')->nullable()->comment('下次订阅时间')->after('expires_date');
            $table->unsignedTinyInteger('auto_renew_status')->default(0)->comment('自动续订状态')->after('is_trial_period');
            $table->string('auto_renew_preference')->default('')->comment('自动续订偏好设置')->after('auto_renew_status');
            $table->text('latest_receipt')->nullable()->comment('订阅票据')->after('auto_renew_preference');
            $table->string('remark')->default('')->comment('备注信息')->after('latest_receipt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropColumn('app_id');
            $table->dropColumn('pay_type');
            $table->dropColumn('currency');
            $table->dropColumn('pay_amount');
            $table->dropColumn('subscribe_success_count');
            $table->dropColumn('subscribe_fail_count');
            $table->dropColumn('grace_period_expires_date');
            $table->dropColumn('renewal_date');
            $table->dropColumn('auto_renew_status');
            $table->dropColumn('auto_renew_preference');
            $table->dropColumn('latest_receipt');
            $table->dropColumn('remark');
        });
    }
};
