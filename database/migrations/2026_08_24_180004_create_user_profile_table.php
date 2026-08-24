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
        Schema::create('user_profile', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->default(0)->comment('用户 ID，登录后同步');
            $table->string('uuid')->unique()->comment('用户唯一标识');
            $table->unsignedInteger('app_id')->comment('应用 ID');
            $table->string('market_channel',32)->comment('应用市场');
            $table->string('version',32)->comment('版本');
            $table->string('name',32)->comment('姓名');
            $table->string('gender',32)->comment('性别');
            $table->dateTime('birth_date')->comment('出生日期');
            $table->string('birth_place')->comment('出生地');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profile');
    }
};
