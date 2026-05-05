<?php

return [
    'enabled' => env('API_OBFUSCATION_ENABLED', false),
    'encryption_enabled' => env('API_OBFUSCATION_ENCRYPTION_ENABLED', false),
    'image_url_rewrite_enabled' => env('API_IMAGE_URL_REWRITE_ENABLED', true),
    'default_image_domain' => env('API_IMAGE_DOMAIN', ''),
    'nonce_cache_prefix' => env('API_OBFUSCATION_NONCE_PREFIX', 'api_obf_nonce:'),
    'nonce_ttl_seconds' => (int) env('API_OBFUSCATION_NONCE_TTL', 300),
    'timestamp_window_seconds' => (int) env('API_OBFUSCATION_TS_WINDOW', 300),
    'packet_version' => env('API_OBFUSCATION_PACKET_VERSION', '1'),

    /*
    |--------------------------------------------------------------------------
    | API Obfuscation Profiles
    |--------------------------------------------------------------------------
    |
    | Use one profile per app to customize outward-facing API identity while
    | keeping internal controllers/services unchanged.
    |
    */
    'profiles' => [
        'default' => [
            'enabled' => false,
            'route_aliases' => [],
            'request_key_map' => [],
            'response_key_map' => [],
            'response_data_key_map' => [],
            'protocol' => [
                'encrypt_request' => false,
                'encrypt_response' => false,
                'allow_plaintext_request' => true,
                'payload_field' => 'payload',
                'sign_field' => 'sign',
                'timestamp_field' => 'ts',
                'nonce_field' => 'nonce',
                'version_field' => 'ver',
            ],
            'security' => [
                'timestamp_window_seconds' => null,
                'nonce_ttl_seconds' => null,
            ],
            'crypto' => [
                'cipher' => 'AES-256-CBC',
                'key' => null,
                'iv' => null,
                'sign_key' => null,
            ],
            'image_url' => [
                'enabled' => false,
                'domain' => null,
                // Empty means scan all string fields recursively.
                'fields' => [],
                'path_prefixes' => ['attach/', '/attach/', 'uploads/attach/', '/uploads/attach/', 'storage/attach/', '/storage/attach/'],
            ],
        ],

    ],
];
