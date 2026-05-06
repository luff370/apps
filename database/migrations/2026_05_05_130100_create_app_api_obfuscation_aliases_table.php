<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_api_obfuscation_aliases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id')->default(0)->comment('应用混淆配置ID');
            $table->unsignedBigInteger('interface_id')->default(0)->comment('公共接口ID');
            $table->string('alias', 64)->default('')->comment('接口别名');
            $table->json('request_key_map')->nullable()->comment('该接口请求参数映射');
            $table->json('response_key_map')->nullable()->comment('该接口响应参数映射');
            $table->json('response_data_key_map')->nullable()->comment('该接口响应data参数映射');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('状态');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->unsignedInteger('create_time')->default(0);
            $table->unsignedInteger('update_time')->default(0);

            $table->unique(['profile_id', 'interface_id'], 'uniq_profile_interface');
            $table->index(['profile_id', 'alias']);
            $table->index(['interface_id']);
            $table->index(['is_enable']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_api_obfuscation_aliases');
    }
};
