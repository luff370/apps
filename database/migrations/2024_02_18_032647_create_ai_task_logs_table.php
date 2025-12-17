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
        Schema::create('ai_task_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('cate_id')->comment('任务来源(任务分类)');
            $table->unsignedInteger('type')->comment('任务类型');
            $table->string('input_content','2000')->comment('输入内容');
            $table->string('return_content','2000')->comment('输出内容');
            $table->string('market_channel',32)->comment('应用渠道');
            $table->string('version',32)->comment('应用版本');
            $table->unsignedTinyInteger('mark')->default(0)->comment('评分：0-无，1-赞，2-踩');
            $table->string('remark')->default('')->comment('备注');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1-成功，0-失败');
            $table->unsignedInteger('create_time')->comment('创建时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_task_logs');
    }
};
