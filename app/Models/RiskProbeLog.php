<?php

namespace App\Models;

class RiskProbeLog extends BaseModel
{
    /**
     * risk_probe_logs 是请求级事实表：每个经过 DeviceEnvRiskMiddleware 的客户端 API 请求一条记录。
     * probe_json 保存脱敏后的完整 Device-Env，behavior_json 保存行为字段子集，普通列用于索引分析。
     */
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
