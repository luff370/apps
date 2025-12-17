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
        Schema::table('user_whitelist', function (Blueprint $table) {
            $table->string('source_device')->default('')->comment('来源设备')->after('source_region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_whitelist', function (Blueprint $table) {
            $table->dropColumn('source_device');
        });
    }
};
