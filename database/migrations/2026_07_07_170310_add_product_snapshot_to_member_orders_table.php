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
        Schema::table('member_orders', function (Blueprint $table) {
            $table->string('product_name', 64)->default('')->comment('下单时产品名称')->after('product_id');
            $table->decimal('product_price', 10, 2)->default(0)->comment('下单时产品价格')->after('product_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_orders', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'product_price']);
        });
    }
};
