<?php

namespace App\Models;

class UserBehaviorReport extends BaseModel
{
    protected $table = 'user_behavior_reports';

    protected $fillable = [
        'user_id', 'app_id', 'uuid', 'device_sn', 'package_name', 'platform', 'app_version',
        'market_channel', 'ip', 'behavior', 'device_environment', 'ad_extension',
        'client_reported_at',
    ];

    protected $casts = [
        'user_id' => 'int',
        'app_id' => 'int',
        'behavior' => 'array',
        'device_environment' => 'array',
        'ad_extension' => 'array',
        'client_reported_at' => 'datetime',
    ];
}
