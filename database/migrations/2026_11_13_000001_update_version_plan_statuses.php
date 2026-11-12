<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_version_plans', function (Blueprint $table) {
            $table->string('status', 30)->default('开发中')->comment('计划状态')->change();
        });

        $planStatuses = ['开发中', '待上架', '已上架', '暂停'];
        $taskStatuses = ['待提交', '已提交', '审核中', '已拒绝', '已上架', '已下架'];

        DB::table('app_version_plans')->where('status', '已完成')->update(['status' => '已上架']);
        DB::table('app_version_plans')->where('status', '已取消')->update(['status' => '暂停']);
        DB::table('app_version_plans')->where('status', '部分完成')->update(['status' => '待上架']);
        DB::table('app_version_plans')->whereNotIn('status', $planStatuses)->update(['status' => '开发中']);

        DB::table('app_version_plan_tasks')->whereIn('status', ['已过审', '待上架'])->update(['status' => '已提交']);
        DB::table('app_version_plan_tasks')->whereNotIn('status', $taskStatuses)->update(['status' => '待提交']);
    }

    public function down(): void
    {
        Schema::table('app_version_plans', function (Blueprint $table) {
            $table->string('status', 30)->default('草稿')->comment('计划状态')->change();
        });
    }
};
