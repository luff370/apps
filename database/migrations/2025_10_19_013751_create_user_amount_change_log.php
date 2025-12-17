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
        Schema::create('user_amount_change_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('app_id')->comment('应用ID');
            $table->unsignedBigInteger('type')->comment('账变类型');
            $table->decimal('amount')->comment('账变金额');
            $table->decimal('before_amount')->comment('账变前余额');
            $table->decimal('after_amount')->comment('账变后余额');
            $table->string('remark')->default('')->comment('备注');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_amount_change_log');
    }
};
