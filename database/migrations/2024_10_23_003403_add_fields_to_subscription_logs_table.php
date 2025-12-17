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
        Schema::table('subscription_logs', function (Blueprint $table) {
            $table->string('notification_type', 32)->default('')->comment('通知类型')->after('id');
            $table->string('sub_type', 32)->default('')->comment('子标题')->after('notification_type');
            $table->unsignedTinyInteger('auto_renew_status')->default(0)->comment('自动续费状态')->after('status');
            $table->unsignedTinyInteger('expiration_intent')->default(0)->comment('续费失败类型')->after('auto_renew_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_logs', function (Blueprint $table) {
            $table->dropColumn('notification_type');
            $table->dropColumn('sub_type');
            $table->dropColumn('auto_renew_status');
            $table->dropColumn('expiration_intent');
        });
    }
};
