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
        Schema::dropIfExists('user_notice');
        Schema::create('user_notice', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedTinyInteger('type')->comment('通知类型');
            $table->string('title', 32)->comment('标题');
            $table->string('content')->comment('消息内容');
            $table->unsignedTinyInteger('status')->comment('状态（0-未发送，1-已发送，2-发送失败）');
            $table->dateTime('planned_push_time')->comment('计划推送时间');
            $table->dateTime('push_time')->comment('推送时间');
            $table->string('msg_id', 32)->default('')->comment('消息ID');
            $table->string('error_msg')->default('')->comment('错误消息');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notice');
    }
};
