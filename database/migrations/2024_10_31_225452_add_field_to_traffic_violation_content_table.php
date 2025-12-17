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
        Schema::table('traffic_violation_content', function (Blueprint $table) {
            $table->unsignedTinyInteger('app_audit_data')->default(0)->comment('是否为app审核数据')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_violation_content', function (Blueprint $table) {
            $table->dropColumn('app_audit_data');
        });
    }
};
