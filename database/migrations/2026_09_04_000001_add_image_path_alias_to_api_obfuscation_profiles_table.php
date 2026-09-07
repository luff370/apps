<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('image_path_alias_enabled')
                ->default(0)
                ->after('image_url_enabled')
                ->comment('图片路径别名');
        });
    }

    public function down(): void
    {
        Schema::table('app_api_obfuscation_profiles', function (Blueprint $table) {
            $table->dropColumn('image_path_alias_enabled');
        });
    }
};
