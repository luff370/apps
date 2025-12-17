<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            //
            $table->unsignedTinyInteger('task_ad_type')->default(0)->comment('激励广告类型(0-关闭,1-激励视频,2-插屏)')->after('is_del');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            //
            $table->dropColumn('task_ad_type');
        });
    }
};
