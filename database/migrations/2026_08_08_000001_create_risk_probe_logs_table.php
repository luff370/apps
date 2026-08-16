<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_probe_logs', function (Blueprint $table) {
            // 请求身份与解析结果：missing/error 也会落库，便于统计旧版本覆盖率和异常率。
            $table->id();
            $table->string('package_name')->nullable()->index();
            $table->unsignedInteger('app_id')->nullable()->index();
            $table->string('route', 255);
            $table->string('request_method', 10);
            $table->string('status', 16)->index();
            $table->string('error')->nullable();

            // 协议与评分结果。nonce 仅保存不可逆摘要，不保存可重放明文。
            $table->char('nonce_hash', 64)->nullable()->index();
            $table->unsignedSmallInteger('probe_v')->nullable()->index();
            $table->unsignedSmallInteger('env_schema_v')->nullable();
            $table->string('platform', 16)->nullable();
            $table->unsignedSmallInteger('risk_score')->default(0)->index();
            $table->json('risk_reasons')->nullable();
            $table->json('validation_errors')->nullable();
            $table->boolean('env_digest_ok')->nullable();
            $table->boolean('env_field_count_ok')->nullable();
            $table->boolean('env_allows_ads')->nullable();
            $table->unsignedTinyInteger('compliance_mode')->default(0)->index();
            $table->unsignedTinyInteger('ad_switch')->default(1);

            // 完整探针 + 行为子集。样本数量冗余为列，便于按时间范围高效聚合。
            $table->json('probe_json')->nullable();
            $table->json('behavior_json')->nullable()->comment('从 Device-Env 提取的行为探针字段');
            $table->unsignedInteger('touch_sample_count')->nullable();
            $table->unsignedInteger('click_sample_count')->nullable();
            $table->unsignedInteger('swipe_sample_count')->nullable();

            // 请求来源。未登录请求主要依靠 device_sn 关联同一设备。
            $table->string('client_ip', 45)->nullable();
            $table->string('app_version', 64)->nullable();
            $table->string('market_channel', 64)->nullable();
            $table->string('user_uuid')->nullable()->index();
            $table->string('device_sn', 255)->nullable()->index()->comment('设备码，Device-Sn 缺失时使用 Uuid');
            $table->timestamps();

            $table->index(['app_id', 'created_at']);
            $table->index(['device_sn', 'created_at']);
            $table->index(['risk_score', 'created_at']);
            $table->index(['touch_sample_count', 'created_at']);
            $table->index(['click_sample_count', 'created_at']);
            $table->index(['swipe_sample_count', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_probe_logs');
    }
};
