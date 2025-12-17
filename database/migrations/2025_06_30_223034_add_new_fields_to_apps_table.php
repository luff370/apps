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
        Schema::table('system_apps', function (Blueprint $table) {
            // 新增字段
            $table->string('secret_key')->default('')->comment('安全密匙');
            $table->string('contact_type')->default('')->comment('联系方式类型');
            $table->string('contact_number')->default('')->comment('联系号码');
            $table->boolean('subscribe_switch')->default(1)->comment('订阅开关');
            $table->string('push_channel')->default('')->comment('推送通道');
            $table->string('uPush_app_key')->default('')->comment('友盟推送Key');
            $table->string('uPush_app_secret')->default('')->comment('友盟推送Secret');
            $table->string('jPush_app_key')->default('')->comment('极光推送Key');
            $table->string('jPush_app_secret')->default('')->comment('极光推送Secret');
            $table->boolean('ad_switch')->default(1)->comment('广告开关');
            $table->string('topon_app_id')->default('')->comment('topon应用ID');
            $table->string('topon_app_key')->default('')->comment('topon应用Key');
            $table->string('pangolin_app_id')->default('')->comment('穿山甲应用ID');
            $table->string('pangolin_app_key')->default('')->comment('穿山甲应用Key');
            $table->string('youlianghui_app_id')->default('')->comment('优量汇应用ID');
            $table->string('youlianghui_app_key')->default('')->comment('优量汇应用Key');
            $table->boolean('allowlist_switch')->default(0)->comment('白名单广告开关');
            $table->string('allowlist_ad_channel')->default('')->comment('白名单广告商');
            $table->string('splash_ad_code')->default('')->comment('开屏代码');
            $table->string('interstitial_ad_code')->default('')->comment('插屏代码');
            $table->string('native_ad_code')->default('')->comment('信息流代码');
            $table->string('banner_ad_code')->default('')->comment('banner代码');
            $table->string('draw_ad_code')->default('')->comment('draw代码');

            $table->dropColumn([
                'launch_image',
                // 'contact_qq',
                // 'follow_us_url',
                'open_subscribe',
                'task_ad_type',
                'push_switch',
                'push_app_key',
                'push_app_secret',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('system_apps', function (Blueprint $table) {
            // 回滚时删除这些字段
            $table->dropColumn([
                'secret_key',
                'contact_type',
                'contact_number',
                'subscribe_switch',
                'push_channel',
                'uPush_app_key',
                'uPush_app_secret',
                'jPush_app_key',
                'jPush_app_secret',
                'ad_switch',
                'topon_app_id',
                'topon_app_key',
                'pangolin_app_id',
                'pangolin_app_key',
                'youlianghui_app_id',
                'youlianghui_app_key',
                'allowlist_switch',
                'allowlist_ad_channel',
                'splash_ad_code',
                'interstitial_ad_code',
                'native_ad_code',
                'banner_ad_code',
                'draw_ad_code',
            ]);

            $table->boolean('open_subscribe')->default(0);
            $table->boolean('task_ad_type')->default(0);
            $table->boolean('push_switch')->default(0);
            $table->string('launch_image')->default('');
            $table->string('push_app_key')->default('');
            $table->string('push_app_secret')->default('');
            // $table->string('contact_qq')->default('');
            // $table->string('follow_us_url')->default('');
        });
    }
};




