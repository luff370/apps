<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppVersionPlansTable extends Migration
{
    public function up(): void
    {
        Schema::create('app_version_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('title', 120)->comment('计划名称');
            $table->string('version', 50)->comment('目标版本');
            $table->string('status', 30)->default('草稿')->comment('计划状态');
            $table->string('owner_name', 80)->default('')->comment('主负责人');
            $table->date('planned_release_at')->nullable()->comment('计划上架日');
            $table->text('remark')->nullable()->comment('计划备注');
            $table->timestamps();

            $table->index(['app_id', 'version']);
            $table->index(['app_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_version_plans');
    }
}
