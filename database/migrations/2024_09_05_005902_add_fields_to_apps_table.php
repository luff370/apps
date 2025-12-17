<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            //
            $table->string('platform', 32)->default('')->comment('应用平台（ios、android）')->after('id');
            $table->string('push_app_key')->default('')->comment('应用推送KEY')->after('is_del');
            $table->string('push_app_secret')->default('')->comment('应用推送密匙')->after('push_app_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            //
            $table->dropColumn('platform');
            $table->dropColumn('push_app_key');
            $table->dropColumn('push_app_secret');
        });
    }
};
