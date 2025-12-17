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
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_sandbox')->default(0)->comment('是否沙箱环境')->after('user_id');
            $table->dateTime('cancellation_date')->nullable()->comment('取消日期')->after('renewal_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropColumn('is_sandbox');
            $table->dropColumn('cancellation_date');
        });
    }
};
