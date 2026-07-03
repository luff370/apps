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
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('device_code', 64)->default('')->after('domain_expired_date')->comment('手机设备编号');
            $table->string('corporate_phone', 20)->default('')->after('device_code')->comment('法人号码');
            $table->string('contact_email', 128)->default('')->after('corporate_phone')->comment('联系邮箱');
            $table->string('qq', 20)->default('')->after('contact_email')->comment('QQ');
            $table->string('wechat', 64)->default('')->after('qq')->comment('微信');
            $table->unsignedTinyInteger('is_enable')->default(1)->after('wechat')->comment('状态：1启用，0停用');
            $table->text('remark')->nullable()->after('is_enable')->comment('备注');
            $table->json('agreement_templates')->nullable()->after('remark')->comment('协议母版配置');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'device_code',
                'corporate_phone',
                'contact_email',
                'qq',
                'wechat',
                'is_enable',
                'remark',
                'agreement_templates',
            ]);
        });
    }
};
