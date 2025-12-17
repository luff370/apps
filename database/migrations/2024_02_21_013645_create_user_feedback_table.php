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
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->string('type', 32)->comment('问题类别');
            $table->string('content', 500)->comment('内容');
            $table->string('images',2000)->comment('问题图片');
            $table->string('email', 100)->comment('联系邮箱');
            $table->string('phone', 100)->comment('联系邮箱');
            $table->unsignedTinyInteger('status')->default(0)->comment('0-未查看，1-已查看，2-已回复');
            $table->string('recover_content')->default('')->comment('回复内容');
            $table->string('admin_name')->default('')->comment('管理员姓名');

            $table->unsignedInteger('create_time');
            $table->unsignedInteger('update_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};
