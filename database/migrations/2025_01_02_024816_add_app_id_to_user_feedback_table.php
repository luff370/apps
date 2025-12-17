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
        Schema::table('user_feedback', function (Blueprint $table) {
            $table->unsignedInteger('app_id')->comment('应用ID')->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_feedback', function (Blueprint $table) {
            $table->dropColumn('app_id');
        });
    }
};
