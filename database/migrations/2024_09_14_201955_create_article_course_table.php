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
        Schema::create('article_course', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nid');
            $table->unsignedTinyInteger('lesson_number')->default(0)->comment('课节序号');
            $table->string('title')->default('')->comment('标题');
            $table->string('image')->default('')->comment('缩略图');
            $table->string('url')->default('')->comment('资源地址');
            $table->string('duration')->default('')->comment('资源时长');
            $table->string('source')->default('')->comment('来源');
            $table->string('code')->default('')->comment('序列号、编码');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('是否启用');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article_course');
    }
};
