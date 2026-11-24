<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('app_version_plans', 'priority')) {
            return;
        }

        Schema::table('app_version_plans', function (Blueprint $table) {
            $table->string('priority', 10)->default('P2')->after('status')->comment('优先级');
        });
    }

    public function down(): void
    {
        Schema::table('app_version_plans', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
