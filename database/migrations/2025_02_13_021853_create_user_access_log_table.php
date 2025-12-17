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
        Schema::create('user_access_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->default(0)->comment('用户ID');
            $table->unsignedInteger('app_id')->default(0)->comment('应用ID');
            $table->string('market_channel', 32)->default('')->comment('市场渠道');
            $table->string('version', 32)->default('')->comment('版本');
            $table->string('os', 32)->default('')->comment('系统');
            $table->string('uuid', 64)->default('')->comment('uuid');
            $table->string('device', 64)->default('')->comment('设备');
            $table->string('ip', 15)->default('')->comment('IP');
            $table->string('region', 64)->default('')->comment('区域');
            $table->string('source', 64)->default('')->comment('来源');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_access_log');
    }
};
