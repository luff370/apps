<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_whitelist_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->default(0);
            $table->unsignedInteger('user_id')->default(0);
            $table->string('uuid', 32)->comment('用户uuid');
            $table->string('platform', 32)->comment('系统平台');
            $table->string('market_channel', 32)->comment('应用市场');
            $table->string('version', 32)->comment('应用版本');
            $table->string('device', 32)->comment('设备号');
            $table->string('ip', 32)->comment('请求IP');
            $table->string('region', 32)->comment('请求地区');
            $table->string('source', 32)->comment('屏蔽来源(device,ip,region)');
            $table->dateTime('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_whitelist_log');
    }
};
