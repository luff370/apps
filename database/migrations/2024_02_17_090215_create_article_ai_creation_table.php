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
        Schema::create('article_ai_creation', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('article_id')->comment('文章ID');
            $table->string('prompt',500)->comment('prompt词');
            $table->string('copy_writing',500)->comment('article预制文案');
            $table->string('params',500)->default('')->comment('article预制文案');
            $table->unsignedTinyInteger('is_return_limit')->default(0)->comment('是否限制返回字数(0-否，1-是)');
            $table->string('return_limit_values')->default('')->comment('字数限制列表');
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
        Schema::dropIfExists('article_ai_creation');
    }
};
