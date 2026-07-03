<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppVersionPlanTasksTable extends Migration
{
    public function up(): void
    {
        Schema::create('app_version_plan_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id')->comment('版本计划ID');
            $table->string('market_channel', 60)->comment('应用市场渠道');
            $table->string('name', 120)->default('')->comment('渠道上架名称');
            $table->string('version', 50)->comment('渠道版本号');
            $table->string('owner_name', 80)->default('')->comment('任务负责人');
            $table->string('status', 30)->default('待提交')->comment('任务状态');
            $table->date('submitted_at')->nullable()->comment('提交日期');
            $table->date('listed_at')->nullable()->comment('上架日期');
            $table->text('remark')->nullable()->comment('任务备注');
            $table->unsignedTinyInteger('is_force')->default(0)->comment('是否强制更新');
            $table->json('force')->nullable()->comment('强更配置');
            $table->timestamps();

            $table->index(['plan_id', 'market_channel']);
            $table->index(['market_channel', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_version_plan_tasks');
    }
}
