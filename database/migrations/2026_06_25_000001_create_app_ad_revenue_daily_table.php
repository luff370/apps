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
        Schema::create('app_ad_revenue_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->comment('统计日期');
            $table->unsignedInteger('app_id')->comment('应用ID');
            $table->string('platform', 32)->default('')->comment('广告平台标识');
            $table->string('platform_name', 64)->default('')->comment('广告平台名称');
            $table->string('slot_id', 128)->default('')->comment('广告位ID');
            $table->string('slot_name', 128)->default('')->comment('广告位名称');
            $table->string('ad_type', 32)->default('')->comment('广告类型');
            $table->unsignedInteger('request_count')->default(0)->comment('请求次数');
            $table->unsignedInteger('success_count')->default(0)->comment('成功次数');
            $table->unsignedInteger('show_count')->default(0)->comment('展示次数');
            $table->unsignedInteger('click_count')->default(0)->comment('点击次数');
            $table->decimal('ad_revenue', 12, 2)->default(0)->comment('广告收益');
            $table->string('data_status', 32)->default('completed')->comment('采集状态');
            $table->string('collect_message', 255)->default('')->comment('采集说明');
            $table->dateTime('collected_at')->nullable()->comment('采集时间');
            $table->timestamps();

            $table->unique(['date', 'app_id', 'platform', 'slot_id'], 'app_ad_revenue_daily_unique');
            $table->index(['app_id', 'date']);
            $table->index(['platform', 'date']);
            $table->index(['data_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_ad_revenue_daily');
    }
};
