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
            $table->unsignedTinyInteger('status')->default(1)->comment('是否启用：1-开启，0-关闭')->after('remark');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_whitelist', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
