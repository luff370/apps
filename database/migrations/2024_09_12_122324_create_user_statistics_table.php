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
        Schema::create('user_statistics', function (Blueprint $table) {
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedInteger('new_users_count')->comment('新增用户数量');
            $table->unsignedInteger('active_users_count')->comment('活跃用户数量');
            $table->date('date')->comment('日期');

            $table->unique(['app_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_statistics');
    }
};
