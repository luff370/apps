<?php

namespace App\Models;

class RiskList extends BaseModel
{
    public const TYPE_BLACKLIST = 'blacklist';
    public const TYPE_WATCHLIST = 'watchlist';

    protected $fillable = [
        'list_type', 'target_type', 'target_value', 'app_id',
        'decision', 'status', 'remark', 'operator',
    ];

    protected $casts = [
        'app_id' => 'int',
        'status' => 'int',
    ];
}
