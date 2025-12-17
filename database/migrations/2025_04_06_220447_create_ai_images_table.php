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
        Schema::create('ai_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('platform')->comment('调用平台');
            $table->string('type')->comment('接口类型');
            $table->string('prompt')->comment('提示词')->nullable()->default('');
            $table->json('params')->comment('请求参数')->nullable();
            $table->string('input_image')->comment('输入图片')->nullable()->default('');
            $table->string('output_image')->comment('输出图片')->nullable()->default('');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_images');
    }
};
