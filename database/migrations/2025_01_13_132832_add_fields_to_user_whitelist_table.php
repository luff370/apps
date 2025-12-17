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
            $table->string('source_ip',32)->default('')->comment('来源IP')->after('content');
            $table->string('source_region')->default('')->comment('来源区域')->after('source_ip');
            $table->string('version')->default('')->comment('应用版本')->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_whitelist', function (Blueprint $table) {
            $table->dropColumn('source_ip');
            $table->dropColumn('source_region');
            $table->dropColumn('version');
        });
    }
};
