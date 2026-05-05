<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_api_interfaces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id')->default(0)->comment('应用ID');
            $table->string('package_name', 120)->default('')->comment('应用包名');
            $table->string('name', 120)->default('')->comment('接口名称');
            $table->string('module', 64)->default('')->comment('模块');
            $table->string('path', 200)->comment('真实接口路径，不含/api前缀');
            $table->string('method', 12)->default('POST')->comment('请求方法');
            $table->string('alias', 32)->default('')->comment('接口别名');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('状态');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->unsignedInteger('create_time')->default(0);
            $table->unsignedInteger('update_time')->default(0);

            $table->index(['app_id']);
            $table->index(['package_name']);
            $table->index(['alias']);
            $table->index(['path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_api_interfaces');
    }
};

