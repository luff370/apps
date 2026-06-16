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
