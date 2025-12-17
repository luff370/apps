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
        Schema::create('application_withdrawal', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->comment('用户ID');
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->decimal('amount', 10, 2)->comment('提现金额');
            $table->string('account', 50)->comment('提现账号');
            $table->string('name', 50)->comment('提现人姓名');
            $table->dateTime('apply_time')->comment('申请时间');
            $table->dateTime('audit_time')->nullable()->comment('审核时间');
            $table->tinyInteger('audit_status')->default(0)->comment('状态 0:待审核 1:审核通过 2:审核不通过');
            $table->string('reply_content', 255)->nullable()->comment('审核回复');
            $table->tinyInteger('status')->default(1)->comment('状态 0:删除 1:正常');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_withdrawal');
    }
};
