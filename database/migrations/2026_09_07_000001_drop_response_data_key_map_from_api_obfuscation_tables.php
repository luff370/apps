<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->dropColumn('response_data_key_map');
        });
        Schema::table('app_api_obfuscation_aliases', function (Blueprint $table) {
            $table->dropColumn('response_data_key_map');
        });
    }

    public function down(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->json('response_data_key_map')->nullable()->comment('响应data字段映射');
        });
        Schema::table('app_api_obfuscation_aliases', function (Blueprint $table) {
            $table->json('response_data_key_map')->nullable()->comment('该接口响应data参数映射');
        });
    }
};
