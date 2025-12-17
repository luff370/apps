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
        Schema::table('system_apps', function (Blueprint $table) {
            //
            $table->boolean('auto_transfer_switch')->default(false)->after('push_switch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            //
            $table->dropColumn('auto_transfer_switch');
        });
    }
};
