<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tianji_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->default(0)->comment('应用ID');
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->string('target_type', 16)->comment('skill 或 data');
            $table->string('target_key', 64)->comment('技能标识或资料标识');
            $table->string('title', 64)->default('')->comment('首问摘要');
            $table->json('report')->nullable()->comment('程序计算报告');
            $table->timestamps();

            $table->index(['user_id', 'app_id', 'target_type', 'target_key'], 'tianji_chat_sessions_target_index');
        });

        Schema::create('tianji_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->comment('对话ID');
            $table->string('role', 16)->comment('user 或 assistant');
            $table->mediumText('content');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->boolean('corrected')->default(false)->comment('是否经过一次格式纠正');
            $table->boolean('valid')->default(true)->comment('是否通过输出协议');
            $table->timestamps();

            $table->index(['session_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tianji_chat_messages');
        Schema::dropIfExists('tianji_chat_sessions');
    }
};
