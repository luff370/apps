<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->string('api_domain', 255)->default('')->after('image_domain')->comment('接口域名');
        });
    }

    public function down(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->dropColumn('api_domain');
        });
    }
};
