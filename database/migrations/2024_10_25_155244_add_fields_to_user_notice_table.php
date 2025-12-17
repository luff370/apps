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
        Schema::table('user_notice', function (Blueprint $table) {
            $table->string('channel', '32')->default('uPush')->comment('推送通道：uPush,jPush');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_notice', function (Blueprint $table) {
            //
            $table->dropColumn('channel');
        });
    }
};
