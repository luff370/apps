<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id')->default(0)->comment('应用ID');
            $table->string('package_name', 120)->default('')->comment('应用包名');
            $table->unsignedTinyInteger('enabled')->default(0)->comment('配置总开关');
            $table->unsignedTinyInteger('encrypt_request')->default(0)->comment('请求加密');
            $table->unsignedTinyInteger('encrypt_response')->default(0)->comment('响应加密');
            $table->unsignedTinyInteger('allow_plaintext_request')->default(1)->comment('允许明文请求');
            $table->unsignedTinyInteger('image_url_enabled')->default(0)->comment('图片域名重写');
            $table->string('image_domain', 255)->default('')->comment('图片域名');
            $table->string('alias_rule', 32)->default('hash4')->comment('别名生成规则');
            $table->json('request_key_map')->nullable()->comment('请求参数映射');
            $table->json('response_key_map')->nullable()->comment('响应字段映射');
            $table->json('response_data_key_map')->nullable()->comment('响应data字段映射');
            $table->json('protocol')->nullable()->comment('协议字段配置');
            $table->json('security')->nullable()->comment('安全窗口配置');
            $table->json('crypto')->nullable()->comment('加密配置');
            $table->json('image_url')->nullable()->comment('图片替换配置');
            $table->json('route_aliases')->nullable()->comment('路由别名映射');
            $table->unsignedInteger('create_time')->default(0);
            $table->unsignedInteger('update_time')->default(0);

            $table->index(['app_id']);
            $table->index(['package_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_api_obfuscation_profiles');
    }
};

