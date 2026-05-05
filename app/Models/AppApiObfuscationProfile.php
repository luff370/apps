<?php

namespace App\Models;

class AppApiObfuscationProfile extends Model
{
    protected $table = 'app_api_obfuscation_profiles';

    const CREATED_AT = 'create_time';
    const UPDATED_AT = 'update_time';

    protected $casts = [
        'app_id' => 'int',
        'enabled' => 'int',
        'encrypt_request' => 'int',
        'encrypt_response' => 'int',
        'allow_plaintext_request' => 'int',
        'image_url_enabled' => 'int',
        'request_key_map' => 'array',
        'response_key_map' => 'array',
        'response_data_key_map' => 'array',
        'protocol' => 'array',
        'security' => 'array',
        'crypto' => 'array',
        'image_url' => 'array',
        'route_aliases' => 'array',
    ];

    protected $fillable = [
        'app_id',
        'package_name',
        'enabled',
        'encrypt_request',
        'encrypt_response',
        'allow_plaintext_request',
        'image_url_enabled',
        'image_domain',
        'alias_rule',
        'request_key_map',
        'response_key_map',
        'response_data_key_map',
        'protocol',
        'security',
        'crypto',
        'image_url',
        'route_aliases',
    ];
}

