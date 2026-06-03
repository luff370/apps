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
        Schema::table('user_whitelist_log', function (Blueprint $table) {
            //
            $table->unsignedTinyInteger('type')->default(0)->comment('屏蔽类型(1-屏蔽广告，2-免费试用)')->after('region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_whitelist_log', function (Blueprint $table) {
            //
            $table->dropColumn('type');
        });
    }
};
