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
        Schema::table('app_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('app_payments', 'sort')) {
                $table->unsignedInteger('sort')->default(0)->comment('排序')->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_payments', function (Blueprint $table) {
            if (Schema::hasColumn('app_payments', 'sort')) {
                $table->dropColumn('sort');
            }
        });
    }
};
