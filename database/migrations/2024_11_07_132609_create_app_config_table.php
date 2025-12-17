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
        Schema::create('app_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('channel', 32)->default('all')->comment('渠道');
            $table->string('version', 32)->default('all')->comment('版本');
            $table->string('name', 32)->default('')->comment('参数名称');
            $table->string('key', 32)->default('')->comment('参数key');
            $table->string('value')->default('')->comment('参数值');
            $table->string('remark')->default('')->comment('备注');
            $table->unsignedTinyInteger('is_enable')->default(1)->comment('是否启用');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_config');
    }
};
