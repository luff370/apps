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
            $table->string('subscribe_fail_reason')->default('')->comment('订阅失败原因')->after('subscribe_fail_count');
            $table->dateTime('grace_period_expires_date')->nullable()->comment('宽限期到期时间')->after('expires_date');
            $table->dateTime('renewal_date')->nullable()->comment('下次订阅时间')->after('expires_date');
            $table->unsignedTinyInteger('auto_renew_status')->default(0)->comment('自动续订状态')->after('is_trial_period');
            $table->string('auto_renew_preference')->default('')->comment('自动续订偏好设置')->after('auto_renew_status');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_orders', function (Blueprint $table) {

            $table->dropColumn('subscribe_fail_reason');
            $table->dropColumn('grace_period_expires_date');
            $table->dropColumn('renewal_date');
            $table->dropColumn('auto_renew_status');
            $table->dropColumn('auto_renew_preference');
            $table->dropColumn('created_at');
            $table->dropColumn('updated_at');
        });
    }
};
