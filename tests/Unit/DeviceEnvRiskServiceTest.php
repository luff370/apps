<?php

namespace Tests\Unit;

use ReflectionMethod;
use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\Services\DeviceEnvRiskService;

class DeviceEnvRiskServiceTest extends TestCase
{
    private DeviceEnvRiskService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->service = new DeviceEnvRiskService();
    }

    public function test_it_decrypts_and_scores_device_env_header(): void
    {
        $sealed = $this->seal([
            'pv' => 5,
            'mk' => true,
            'ea' => false,
            'ts' => time(),
            'nc' => 'nonce-1',
            'ver' => '1',
        ]);

        $request = Request::create('/api/app/info', 'POST', [], [], [], [
            'HTTP_APP_ID' => '10048',
            'HTTP_PACKAGE_NAME' => 'com.he.dahu',
            'HTTP_DEVICE_ENV' => $sealed,
        ]);

        $context = $this->service->inspect($request);

        $this->assertSame('ok', $context['status']);
        $this->assertSame(5, $context['probe']['probe_v']);
        $this->assertTrue($context['probe']['is_monkey']);
        $this->assertSame(1, $context['decision']['compliance_mode']);
        $this->assertSame(0, $context['decision']['ad_switch']);
        $this->assertContains('monkey', $context['reasons']);
    }

    public function test_it_rejects_replayed_nonce(): void
    {
        $sealed = $this->seal([
            'pv' => 5,
            'ts' => time(),
            'nc' => 'nonce-2',
            'ver' => '1',
        ]);

        $request = Request::create('/api/app/info', 'POST', [], [], [], [
            'HTTP_APP_ID' => '10048',
            'HTTP_PACKAGE_NAME' => 'com.he.dahu',
            'HTTP_DEVICE_ENV' => $sealed,
        ]);

        $this->assertSame('ok', $this->service->inspect($request)['status']);

        $replayed = $this->service->inspect($request);
        $this->assertSame('error', $replayed['status']);
        $this->assertSame('replayed device env', $replayed['error']);
    }

    public function test_it_restores_all_behavior_wire_keys(): void
    {
        $sealed = $this->seal([
            'tc' => 12, 'tt' => 41, 'td' => 33,
            'tp' => 0.18, 'tv' => 0.24, 'tz' => 0.05,
            'cc' => 4, 'ct' => 62, 'cb' => 0.12, 'ci' => 0.95,
            'wc' => 3, 'wl' => 0.91, 'ws' => 0.84, 'wj' => 0.08,
            'sg' => 15, 'gs' => 19, 'it' => 21,
            'ts' => time(), 'nc' => 'behavior-wire-keys', 'ver' => '1',
        ]);

        $request = Request::create('/api/user/behavior/report', 'POST', [], [], [], [
            'HTTP_APP_ID' => '10048',
            'HTTP_PACKAGE_NAME' => 'com.he.dahu',
            'HTTP_DEVICE_ENV' => $sealed,
        ]);

        $probe = $this->service->inspect($request)['probe'];

        $this->assertSame(0.18, $probe['touch_pressure_variation']);
        $this->assertSame(4, $probe['click_sample_count']);
        $this->assertSame(0.91, $probe['swipe_linearity']);
        $this->assertSame(19, $probe['gyro_static_score']);
        $this->assertSame(21, $probe['imu_static_during_touch']);
    }

    public function test_app_info_policy_overrides_ads_for_high_risk_context(): void
    {
        $data = [
            'ad_switch' => 1,
            'is_free_ad' => 0,
            'topon_app_id' => 'app-id',
        ];

        $result = $this->service->applyAppInfoPolicy($data, [
            'status' => 'ok',
            'decision' => [
                'compliance_mode' => 1,
                'ad_switch' => 0,
            ],
        ]);

        $this->assertSame(0, $result['ad_switch']);
        $this->assertSame(1, $result['compliance_mode']);
        $this->assertSame(1, $result['complianceMode']);
        $this->assertSame(0, $result['is_free_ad']);
    }

    public function test_it_validates_probe_v9_schema_and_digest(): void
    {
        $payload = [
            'pv' => 9,
            'pf' => 'android',
            'np' => true,
            'nv' => 1,
            'nq' => true,
            'nm' => true,
            'ea' => true,
            'ev' => 2,
            'ef' => 7,
        ];
        $payload['eg'] = $this->digest($payload);
        $payload += ['ts' => time(), 'nc' => 'nonce-v9-valid', 'ver' => '1'];

        $context = $this->service->inspect($this->requestWith($this->seal($payload)));

        $this->assertSame('ok', $context['status']);
        $this->assertSame(9, $context['probe']['probe_v']);
        $this->assertTrue($context['probe']['native_probe_ok']);
        $this->assertTrue($context['validation']['env_digest_ok']);
        $this->assertTrue($context['validation']['env_field_count_ok']);
        $this->assertSame([], $context['validation']['errors']);
    }

    public function test_probe_v9_schema_failures_are_scored_without_rejecting_request(): void
    {
        $payload = [
            'pv' => 9,
            'pf' => 'android',
            'np' => false,
            'ev' => 1,
            'ef' => 99,
            'eg' => '0000000000000000',
            'ts' => time(),
            'nc' => 'nonce-v9-invalid',
            'ver' => '1',
        ];

        $context = $this->service->inspect($this->requestWith($this->seal($payload)));

        $this->assertSame('ok', $context['status']);
        $this->assertContains('env_schema_v_invalid', $context['validation']['errors']);
        $this->assertContains('env_field_count_mismatch', $context['validation']['errors']);
        $this->assertContains('device_env_digest_mismatch', $context['validation']['errors']);
        $this->assertContains('native_probe_protocol_mismatch', $context['validation']['errors']);
        $this->assertGreaterThanOrEqual(30, $context['score']);
    }

    public function test_behavior_rules_require_complete_signal_combinations(): void
    {
        $incomplete = $this->inspectPayload([
            'tc' => 8, 'pz' => 95, 'pr' => 5, 'cv' => 30,
            'wc' => 2, 'wl' => 99, 'wu' => 99, 'wj' => 30,
            'gy' => 95, 'iu' => 95,
        ], 'behavior-incomplete');

        $this->assertNotContains('touch_biometrics_synthetic', $incomplete['reasons']);
        $this->assertNotContains('swipe_synthetic', $incomplete['reasons']);
        $this->assertContains('imu_static_during_activity', $incomplete['reasons']);

        // IMU 完整组合本身为 30 分；其余两组因缺字段均不能加分。
        $this->assertSame(30, $incomplete['score']);
    }

    public function test_complete_behavior_combinations_trigger_compliance_and_cap_score(): void
    {
        $context = $this->inspectPayload([
            'tc' => 8, 'tt' => 10, 'td' => 10,
            'pz' => 95, 'pr' => 5, 'cv' => 5,
            'kc' => 3, 'ke' => 10, 'kb' => 90,
            'wc' => 2, 'wl' => 99, 'wu' => 99, 'wj' => 5,
            'gy' => 95, 'iu' => 95, 'sg' => 95,
        ], 'behavior-complete');

        $this->assertSame(100, $context['score']);
        $this->assertSame(1, $context['decision']['compliance_mode']);
        $this->assertContains('touch_biometrics_synthetic', $context['reasons']);
        $this->assertContains('swipe_synthetic', $context['reasons']);
        $this->assertContains('imu_static_during_activity', $context['reasons']);
    }

    public function test_sensor_or_gyro_static_alone_does_not_reach_ad_block_threshold(): void
    {
        $sensor = $this->inspectPayload(['sg' => 90], 'sensor-alone');
        $gyro = $this->inspectPayload(['gy' => 90], 'gyro-alone');

        $this->assertSame(25, $sensor['score']);
        $this->assertSame(1, $sensor['decision']['ad_switch']);
        $this->assertSame(0, $gyro['score']);
    }

    private function inspectPayload(array $payload, string $nonce): array
    {
        $payload += ['ts' => time(), 'nc' => $nonce, 'ver' => '1'];
        return $this->service->inspect($this->requestWith($this->seal($payload)));
    }

    private function requestWith(string $sealed): Request
    {
        return Request::create('/api/app/info', 'POST', [], [], [], [
            'HTTP_APP_ID' => '10048',
            'HTTP_PACKAGE_NAME' => 'com.he.dahu',
            'HTTP_DEVICE_ENV' => $sealed,
        ]);
    }

    private function digest(array $payload): string
    {
        unset($payload['eg'], $payload['ts'], $payload['nc'], $payload['ver']);
        ksort($payload, SORT_STRING);

        // 服务端会先把 wire key 还原为逻辑字段名，再进行 canonical JSON 摘要。
        $logical = [];
        $map = [
            'pv' => 'probe_v', 'pf' => 'platform', 'np' => 'native_probe_ok',
            'nv' => 'native_protocol_v', 'nq' => 'native_channel_ok',
            'nm' => 'native_method_ok', 'ea' => 'env_allows_ads',
            'ev' => 'env_schema_v', 'ef' => 'env_field_count',
        ];
        foreach ($payload as $key => $value) {
            $logical[$map[$key] ?? $key] = $value;
        }
        ksort($logical, SORT_STRING);

        return substr(hash('sha256', json_encode($logical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)), 0, 16);
    }

    private function seal(array $payload, string $packageName = 'com.he.dahu', string $appId = '10048'): string
    {
        $keys = $this->deriveKeys($packageName, $appId);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $cipher = openssl_encrypt($json, 'AES-256-CBC', $keys['aes_key'], OPENSSL_RAW_DATA, $keys['aes_iv']);
        $base64 = base64_encode($cipher ?: '');
        $hmac = hash_hmac('sha256', $base64, $keys['sign_key']);

        return "1.{$hmac}.{$base64}";
    }

    private function deriveKeys(string $packageName, string $appId): array
    {
        $method = new ReflectionMethod(DeviceEnvRiskService::class, 'deriveKeys');
        $method->setAccessible(true);

        return $method->invoke($this->service, $packageName, $appId);
    }
}
