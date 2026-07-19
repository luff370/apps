<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_api_obfuscation_aliases', function (Blueprint $table) {
            $table->json('request_origin_params')->nullable()->after('alias')->comment('请求原始参数快照');
            $table->json('response_origin_params')->nullable()->after('request_origin_params')->comment('响应原始参数快照');
        });
    }

    public function down(): void
    {
        Schema::table('app_api_obfuscation_aliases', function (Blueprint $table) {
            $table->dropColumn(['request_origin_params', 'response_origin_params']);
        });
    }
};
