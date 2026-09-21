<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\RiskProbeLog;
use App\Support\Services\DeviceEnvRiskView;

class DeviceEnvRiskViewTest extends TestCase
{
    public function test_it_maps_score_to_page_level_and_decision(): void
    {
        $this->assertSame('normal', DeviceEnvRiskView::level(10));
        $this->assertSame('watch', DeviceEnvRiskView::level(30));
        $this->assertSame('high', DeviceEnvRiskView::level(70));
        $this->assertSame('critical', DeviceEnvRiskView::level(90));

        $this->assertSame('pass', DeviceEnvRiskView::decision(10, 0, 1));
        $this->assertSame('verify', DeviceEnvRiskView::decision(40, 0, 1));
        $this->assertSame('limit', DeviceEnvRiskView::decision(50, 0, 0));
        $this->assertSame('block', DeviceEnvRiskView::decision(50, 1, 0));
    }

    public function test_it_formats_probe_log_for_admin_pages(): void
    {
        $log = new RiskProbeLog([
            'user_uuid' => 'dev-1',
            'device_sn' => 'sn-1',
            'app_id' => 12,
            'market_channel' => 'xiaomi',
            'app_version' => '1.2.0',
            'risk_score' => 80,
            'risk_reasons' => ['emulator', 'hook'],
            'compliance_mode' => 1,
            'ad_switch' => 0,
            'env_allows_ads' => false,
            'probe_v' => 9,
            'probe_json' => ['network_transport' => 'wifi'],
        ]);
        $log->id = 88;
        $log->created_at = now();

        $row = DeviceEnvRiskView::formatLog($log, [12 => 'Demo App'], ['xiaomi' => '小米']);

        $this->assertSame(88, $row['id']);
        $this->assertSame('dev-1', $row['device_identity']);
        $this->assertSame('Demo App', $row['app_name']);
        $this->assertSame('high', $row['risk_level']);
        $this->assertSame('block', $row['decision']);
        $this->assertSame('guard', $row['event_type']);
        $this->assertSame(0, $row['allows_ads']);
        $this->assertSame('emulator+hook', $row['block_reason']);
        $this->assertSame(80, $row['hook_score']);
        $this->assertSame(80, $row['emulator_score']);
        $this->assertSame('wifi', $row['network_type']);
    }
}
