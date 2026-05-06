<?php

namespace App\Models;

class AppApiObfuscationAlias extends Model
{
    protected $table = 'app_api_obfuscation_aliases';

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $casts = [
        'profile_id' => 'int',
        'interface_id' => 'int',
        'request_key_map' => 'array',
        'response_key_map' => 'array',
        'response_data_key_map' => 'array',
        'is_enable' => 'int',
    ];

    protected $fillable = [
        'profile_id',
        'interface_id',
        'alias',
        'request_key_map',
        'response_key_map',
        'response_data_key_map',
        'is_enable',
        'remark',
    ];

    public function apiInterface()
    {
        return $this->belongsTo(SystemApiInterface::class, 'interface_id');
    }
}
