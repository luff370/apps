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
        Schema::create('article_ai_dialogue', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('article_id')->comment('文章ID');
            $table->string('prompt',500)->comment('prompt词');
            $table->string('greeting')->comment('欢迎语');
            $table->string('params',500)->default('')->comment('预制内容');

            $table->unsignedInteger('create_time')->comment('创建时间');
            $table->unsignedInteger('update_time')->comment('更新时间');

            $table->index('article_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_ai_dialogue');
    }
};
