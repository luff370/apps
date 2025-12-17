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
        Schema::create('user_whitelist', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('platform', 32)->default('')->comment('系统平台');
            $table->string('market_channel', 32)->default('')->comment('应用市场');
            $table->string('way', 32)->default('')->comment('屏蔽方式(region,ip,device)');
            $table->string('content', 64)->default('')->comment('城市');
            $table->unsignedTinyInteger('type')->default(0)->comment('白名单类型(1-屏蔽广告，2-免费试用)');
            $table->unsignedTinyInteger('source')->default(0)->comment('白名单来源(1-手动添加，2-ip白名单)');
            $table->string('remark')->comment('备注信息');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_whitelist');
    }
};
