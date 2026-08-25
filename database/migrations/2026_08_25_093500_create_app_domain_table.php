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
        Schema::create('app_domain', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 191)->comment('域名');
            $table->string('subject', 191)->default('')->comment('主体');
            $table->date('expire_at')->nullable()->comment('到期日期');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：1启用 0停用');
            $table->unsignedTinyInteger('risk_level')->default(1)->comment('风险等级：1低 2中 3高');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();

            $table->unique('domain');
            $table->index('subject');
            $table->index('risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_domain');
    }
};
