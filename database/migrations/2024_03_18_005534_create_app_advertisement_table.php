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
        Schema::create('app_advertisement', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('');
            $table->string('title', '32')->comment('');
            $table->string('market_channel', '32')->comment('');
            $table->string('position', 32)->comment('');
            $table->unsignedTinyInteger('type')->comment('');
            $table->unsignedTinyInteger('status')->default(1)->comment('1-启用，0-停用');
            $table->json('channels')->comment('广告平台');

            $table->unsignedInteger('create_time');
            $table->unsignedInteger('update_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_advertisement');
    }
};
