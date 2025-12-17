<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('food_additives', function (Blueprint $table) {
            $table->id();
            $table->string('name',500)->index()->comment('添加剂名称'); // 添加剂名称
            $table->string('function',500)->nullable()->comment('功能类别'); // 功能类别
            $table->string('food_category')->nullable()->comment('适用食品类别'); // 适用食品类别
            $table->string('max_usage',500)->nullable()->comment('最大使用量'); // 最大使用量
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_additives');
    }
};
