<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_version_plan_tasks', function (Blueprint $table) {
            $table->dateTime('submitted_at')->nullable()->comment('提交时间')->change();
            $table->dateTime('listed_at')->nullable()->comment('上架时间')->change();
        });
    }

    public function down(): void
    {
        Schema::table('app_version_plan_tasks', function (Blueprint $table) {
            $table->date('submitted_at')->nullable()->comment('提交日期')->change();
            $table->date('listed_at')->nullable()->comment('上架日期')->change();
        });
    }
};
