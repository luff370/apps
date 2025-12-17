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
        Schema::table('user_withdrawal', function (Blueprint $table) {
            //
            $table->unsignedTinyInteger('today_withdrawal_count_mark')->default(1)->comment('今日提现次数标记：1-已提现，2-已重置');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_withdrawal', function (Blueprint $table) {
            //
            $table->dropColumn('today_withdrawal_count_mark');
        });
    }
};
