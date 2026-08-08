<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\RiskProbeLog;
use App\Support\Services\RiskProbeAuditService;

class RiskProbeAuditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('risk_probe_logs');
        Schema::create('risk_probe_logs', function (Blueprint $table) {
            $table->id();
            $table->string('package_name')->nullable();
            $table->unsignedInteger('app_id')->nullable();
            $table->string('route');
            $table->string('request_method', 10);
            $table->string('status', 16);
            $table->string('error')->nullable();
            $table->char('nonce_hash', 64)->nullable();
            $table->unsignedSmallInteger('probe_v')->nullable();
            $table->unsignedSmallInteger('env_schema_v')->nullable();
            $table->string('platform', 16)->nullable();
            $table->unsignedSmallInteger('risk_score');
            $table->json('risk_reasons')->nullable();
            $table->json('validation_errors')->nullable();
            $table->boolean('env_digest_ok')->nullable();
            $table->boolean('env_field_count_ok')->nullable();
            $table->boolean('env_allows_ads')->nullable();
            $table->unsignedTinyInteger('compliance_mode');
            $table->unsignedTinyInteger('ad_switch');
            $table->json('probe_json')->nullable();
            $table->json('behavior_json')->nullable();
            $table->unsignedInteger('touch_sample_count')->nullable();
            $table->unsignedInteger('click_sample_count')->nullable();
            $table->unsignedInteger('swipe_sample_count')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->string('app_version')->nullable();
            $table->string('market_channel')->nullable();
            $table->string('user_uuid')->nullable();
            $table->string('device_sn')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_persists_analysis_fields_without_replay_nonce(): void
    {
        $request = Request::create('/api/app/info', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_APP_VERSION' => '1.2.3',
            'HTTP_DEVICE_SN' => 'device-001',
        ]);
        $context = [
            'status' => 'ok',
            'package_name' => 'com.example.app',
            'app_id' => '123',
            'probe' => [
                'probe_v' => 9, 'env_schema_v' => 2, 'platform' => 'android',
                'nc' => 'secret-nonce', 'env_allows_ads' => false,
                'touch_sample_count' => 12, 'touch_timing_entropy' => 55,
                'click_sample_count' => 3, 'swipe_sample_count' => 2,
            ],
            'score' => 60,
            'reasons' => ['env_blocks_ads'],
            'validation' => ['errors' => [], 'env_digest_ok' => true, 'env_field_count_ok' => true],
            'decision' => ['compliance_mode' => 1, 'ad_switch' => 0],
        ];

        (new RiskProbeAuditService())->record($request, $context);

        $log = RiskProbeLog::query()->firstOrFail();
        $this->assertSame(hash('sha256', 'secret-nonce'), $log->nonce_hash);
        $this->assertArrayNotHasKey('nc', $log->probe_json);
        $this->assertSame(60, $log->risk_score);
        $this->assertSame(1, $log->compliance_mode);
        $this->assertSame('device-001', $log->device_sn);
        $this->assertSame(12, $log->touch_sample_count);
        $this->assertSame(55, $log->behavior_json['touch_timing_entropy']);
        $this->assertArrayNotHasKey('env_allows_ads', $log->behavior_json);
    }
}
