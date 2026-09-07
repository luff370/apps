<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->dropColumn(['alias_rule', 'request_key_map']);
        });
    }

    public function down(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->string('alias_rule', 32)->default('hash4')->comment('别名生成规则');
            $table->json('request_key_map')->nullable()->comment('请求参数映射');
        });
    }
};
