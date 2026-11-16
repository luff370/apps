<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_feedback', function (Blueprint $table) {
            $table->string('uuid', 128)->default('')->comment('提交设备标识');
            $table->string('device')->default('')->comment('提交设备编码');
            $table->string('platform', 32)->default('')->comment('提交设备平台');
            $table->string('os_version', 64)->default('')->comment('提交设备系统版本');
            $table->string('ip', 45)->default('')->comment('提交IP');
            $table->index(['app_id', 'uuid', 'user_id']);
            $table->index(['app_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_feedback', function (Blueprint $table) {
            $table->dropIndex(['app_id', 'uuid', 'user_id']);
            $table->dropIndex(['app_id', 'user_id']);
            $table->dropColumn(['uuid', 'device', 'platform', 'os_version', 'ip']);
        });
    }
};
