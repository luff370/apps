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
        Schema::create('ad_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('uuid', 255)->comment('用户uuid');
            $table->unsignedBigInteger('ad_id')->comment('广告应用ID');
            $table->string('ad_code', 32)->comment('广告位编码');
            $table->string('ad_type', 32)->comment('广告类别');
            $table->string('ad_channel', 32)->comment('广告通道');
            $table->string('ad_index', 32)->comment('广告位置');
            $table->tinyInteger('status')->default(0)->comment('请求状态(0:成功，-1失败)');
            $table->string('error_code')->comment('错误码');
            $table->string('error_msg')->comment('错误信息');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_access_logs');
    }
};
