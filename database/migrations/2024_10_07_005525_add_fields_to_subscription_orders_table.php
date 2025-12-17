<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->string('notification_uuid', 64)->default('')->comment('通知唯一ID')->after('id');
            $table->string('subscribe_fail_reason')->default('')->comment('订阅失败原因')->after('status');
            $table->string('remark')->default('')->comment('备注信息')->after('subscribe_fail_reason');
            $table->dateTime('grace_period_expires_date')->nullable()->comment('宽限期到期时间')->after('expires_date');
            $table->dateTime('renewal_date')->nullable()->comment('下次订阅时间')->after('expires_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropColumn('notification_uuid');
            $table->dropColumn('subscribe_fail_reason');
            $table->dropColumn('remark');
            $table->dropColumn('grace_period_expires_date');
            $table->dropColumn('renewal_date');
        });
    }
};
