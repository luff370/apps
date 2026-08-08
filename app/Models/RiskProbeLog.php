<?php

namespace App\Models;

class RiskProbeLog extends BaseModel
{
    protected $fillable = [
        'package_name', 'app_id', 'route', 'request_method', 'status', 'error',
        'nonce_hash', 'probe_v', 'env_schema_v', 'platform', 'risk_score',
        'risk_reasons', 'validation_errors', 'env_digest_ok', 'env_field_count_ok',
        'env_allows_ads', 'compliance_mode', 'ad_switch', 'probe_json',
        'behavior_json', 'touch_sample_count', 'click_sample_count', 'swipe_sample_count',
        'client_ip', 'app_version', 'market_channel', 'user_uuid', 'device_sn',
    ];

    protected $casts = [
        'risk_reasons' => 'array',
        'validation_errors' => 'array',
        'probe_json' => 'array',
        'behavior_json' => 'array',
        'env_digest_ok' => 'boolean',
        'env_field_count_ok' => 'boolean',
        'env_allows_ads' => 'boolean',
    ];
}
