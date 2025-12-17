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
        Schema::create('article', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedInteger('cate_id')->comment('分类ID');
            $table->string('title')->comment('标题');
            $table->string('sub_title')->default('')->comment('子标题');
            $table->string('image')->default('')->comment('封面图、缩络图');
            $table->string('resource_url')->default('')->comment('资源地址');
            $table->string('label')->default('')->comment('标签');
            $table->string('keyword')->default('')->comment('关键字');
            $table->string('code')->default('')->comment('序列号、编码');
            $table->string('remark')->default('')->comment('备注');
            $table->unsignedTinyInteger('is_hot')->default(0)->comment('是否热门：0-否，1-是');
            $table->unsignedTinyInteger('is_recommend')->default(0)->comment('是否推荐：0-否，1-是');
            $table->unsignedTinyInteger('status')->default(1)->comment('是否启用：0-否，1-是');
            $table->unsignedTinyInteger('type')->default(1)->comment('内容类别：1-富文本，2-AI对话，3-AI生成，4-AI绘画，5-单课程，6-合集课程');

            $table->unsignedInteger('create_time');
            $table->unsignedInteger('update_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('article');
    }
};
