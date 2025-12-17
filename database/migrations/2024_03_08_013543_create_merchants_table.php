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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->comment('公司名称');
            $table->unsignedTinyInteger('type')->default(1)->comment('企业类型：1-有限责任公司，2-个体工商户，3-个人');
            $table->string('corporate', 32)->comment('企业法人');
            $table->string('registered_address')->comment('注册地址');

            $table->unsignedInteger('create_time');
            $table->unsignedInteger('update_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
