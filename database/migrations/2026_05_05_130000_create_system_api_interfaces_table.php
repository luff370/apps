<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_api_interfaces', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->default('')->comment('接口名称');
            $table->string('module', 64)->default('')->comment('模块');
            $table->string('path', 200)->comment('真实接口路径，不含/api前缀');
            $table->string('method', 12)->default('POST')->comment('请求方法');
            $table->json('request_params')->nullable()->comment('请求参数定义');
            $table->json('response_params')->nullable()->comment('响应参数定义');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('状态');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->unsignedInteger('create_time')->default(0);
            $table->unsignedInteger('update_time')->default(0);

            $table->unique(['method', 'path'], 'uniq_method_path');
            $table->index(['module']);
            $table->index(['is_enable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_api_interfaces');
    }
};
