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
        Schema::create('traffic_violation_content', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('type')->comment('举报类型');
            $table->json('images')->nullable()->comment('违法照片');
            $table->string('address')->comment('违法地点');
            $table->string('description')->default('')->comment('违法描述');
            $table->unsignedInteger('province_code')->comment('省ID');
            $table->string('license_plate_number', 12)->comment('车牌号码');
            $table->dateTime('violation_time')->comment('违法时间');
            $table->unsignedTinyInteger('is_exposure')->default(0)->comment('是否公开');
            $table->unsignedTinyInteger('audit_status')->default(0)->comment('审核状态(0-待审核，1-审核通过，2-审核不通过)');
            $table->unsignedInteger('audit_user_id')->nullable()->comment('审核人ID');
            $table->dateTime('audit_time')->nullable()->comment('审核时间');
            $table->string('reply_content', 255)->nullable()->comment('审核回复');
            $table->unsignedTinyInteger('reward_type')->default(0)->comment('奖励类型(0-正能量，1-积分，2-现金)');
            $table->unsignedInteger('reward_count')->default(0)->comment('奖励数量');
            $table->string('app_platform')->comment('终端平台');
            $table->string('app_version', 15)->comment('应用版本');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态(0-停用，1-正常)');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_violation_content');
    }
};
