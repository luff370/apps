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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('name')->default('')->comment('任务名称');
            $table->string('type')->default('')->comment('任务类别(incentive_ad-激励广告)');
            $table->string('ad_id')->default('')->comment('广告ID');
            $table->string('frequency')->default('')->comment('任务频次(total-总次数，year-年，month-月，day-天)');
            $table->unsignedInteger('count')->comment('总次数');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
