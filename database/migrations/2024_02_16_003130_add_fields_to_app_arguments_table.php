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
        Schema::table('app_agreements', function (Blueprint $table) {
            $table->unsignedInteger('app_id')->comment('应用ID')->after('id');
            $table->string('key', 32)->default('')->comment('参数键名')->after('type');
            $table->string('platform', 32)->default('')->comment('应用平台（ios android）')->after('key');
            $table->string('version', 32)->default('')->comment('应用版本')->after('platform');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_agreements', function (Blueprint $table) {
            $table->dropColumn('app_id');
            $table->dropColumn('key');
            $table->dropColumn('platform');
            $table->dropColumn('version');
        });
    }
};
