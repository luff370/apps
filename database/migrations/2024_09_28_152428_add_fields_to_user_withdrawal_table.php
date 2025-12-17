<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_withdrawal', function (Blueprint $table) {
            $table->string('transfer_order_no')->default('')->comment('转账订单号')->after('id');
            $table->unsignedTinyInteger('transfer_status')->default(0)->comment('转账状态')->after('status');
            $table->string('transfer_error_msg')->default('')->comment('转账错误信息')->after('transfer_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_withdrawal', function (Blueprint $table) {
            //
            $table->dropColumn('transfer_order_no');
            $table->dropColumn('transfer_status');
            $table->dropColumn('transfer_error_msg');
        });
    }
};
