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
        Schema::table('app_domain', function (Blueprint $table) {
            $table->string('icp_website', 191)->default('')->comment('备案网站')->after('subject');
            $table->string('icp_number', 64)->default('')->comment('备案号')->after('icp_website');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_domain', function (Blueprint $table) {
            $table->dropColumn(['icp_website', 'icp_number']);
        });
    }
};
