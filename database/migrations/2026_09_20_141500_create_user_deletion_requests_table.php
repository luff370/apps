<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedInteger('user_id')->default(0)->comment('匹配到的用户ID');
            $table->string('identifier', 191)->comment('用户提交的账号标识');
            $table->string('email', 100)->default('')->comment('联系邮箱');
            $table->string('reason', 500)->default('')->comment('申请说明');
            $table->unsignedTinyInteger('status')->default(0)->comment('0待处理 1已删除 2未匹配');
            $table->string('ip', 64)->default('')->comment('提交IP');
            $table->string('user_agent', 500)->default('')->comment('UA');
            $table->string('remark', 255)->default('')->comment('处理备注');
            $table->unsignedInteger('processed_at')->nullable()->comment('处理时间');
            $table->unsignedInteger('create_time');
            $table->unsignedInteger('update_time');

            $table->index(['app_id', 'status']);
            $table->index('identifier');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_deletion_requests');
    }
};
