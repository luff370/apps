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
        Schema::create('traffic_signs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cate_id')->comment('分类ID');
            $table->string('title',32)->comment('标题');
            $table->string('url')->comment('图片地址');
            $table->unsignedInteger('sort')->comment('排序');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traffic_signs');
    }
};
