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
                'path_prefixes' => ['attach/', '/attach/', 'uploads/attach/', '/uploads/attach/'],
            ],
        ],

        // App A (App-Id: 10001)
        '10001' => [
            'enabled' => true,
            'route_aliases' => [
                'j9a1' => ['path' => 'auth/login_by_uuid', 'method' => 'POST'],
                'r2q7' => ['path' => 'user/info', 'method' => 'POST'],
                't6x4' => ['path' => 'content/list', 'method' => 'POST'],
                'p8k3' => ['path' => 'payment/order/status', 'method' => 'POST'],
            ],
            'request_key_map' => [
                'u' => 'uuid',
                'tk' => 'token',
                'pg' => 'page',
                'sz' => 'limit',
                'kw' => 'keywords',
            ],
            'response_key_map' => [
                'status' => 's',
                'msg' => 'm',
                'data' => 'd',
                'code' => 'c',
            ],
            'response_data_key_map' => [
                'token' => 'st',
                'user_info' => 'ui',
                'list' => 'ls',
                'total' => 'tt',
            ],
            'protocol' => [
                'encrypt_request' => true,
                'encrypt_response' => true,
                'allow_plaintext_request' => true,
                'payload_field' => 'bx',
                'sign_field' => 'sg',
                'timestamp_field' => 'tm',
                'nonce_field' => 'nn',
                'version_field' => 'vr',
            ],
            'security' => [
                'timestamp_window_seconds' => 300,
                'nonce_ttl_seconds' => 300,
            ],
            'crypto' => [
                'cipher' => 'AES-256-CBC',
                'key' => env('APP10001_OBF_KEY'),
                'iv' => env('APP10001_OBF_IV'),
                'sign_key' => env('APP10001_OBF_SIGN_KEY'),
            ],
            'image_url' => [
                'enabled' => true,
                'domain' => env('APP10001_IMAGE_DOMAIN', 'https://img-a.example.com'),
                'fields' => ['avatar', 'cover', 'image', 'images', 'thumb', 'url'],
            ],
        ],

        // App B (App-Id: 10002)
        '10002' => [
            'enabled' => true,
            'route_aliases' => [
                'a4m8' => ['path' => 'auth/login_by_uuid', 'method' => 'POST'],
                'f1v6' => ['path' => 'task/status', 'method' => 'POST'],
                'u7w2' => ['path' => 'ad/list', 'method' => 'POST'],
                'n5z9' => ['path' => 'chatAI/dialogue', 'method' => 'POST'],
            ],
            'request_key_map' => [
                'did' => 'device_sn',
                'sid' => 'token',
                'pn' => 'page',
                'ps' => 'limit',
                'ct' => 'content',
            ],
            'response_key_map' => [
                'status' => 'ret',
                'msg' => 'note',
                'data' => 'body',
                'code' => 'errno',
            ],
            'response_data_key_map' => [
                'token' => 'sid',
                'list' => 'rows',
                'total' => 'count',
                'member_info' => 'vip',
            ],
            'protocol' => [
                'encrypt_request' => true,
                'encrypt_response' => true,
                'allow_plaintext_request' => true,
                'payload_field' => 'p',
                'sign_field' => 'h',
                'timestamp_field' => 't',
                'nonce_field' => 'n',
                'version_field' => 'v',
            ],
            'security' => [
                'timestamp_window_seconds' => 240,
                'nonce_ttl_seconds' => 240,
            ],
            'crypto' => [
                'cipher' => 'AES-256-CBC',
                'key' => env('APP10002_OBF_KEY'),
                'iv' => env('APP10002_OBF_IV'),
                'sign_key' => env('APP10002_OBF_SIGN_KEY'),
            ],
            'image_url' => [
                'enabled' => true,
                'domain' => env('APP10002_IMAGE_DOMAIN', 'https://img-b.example.com'),
                'fields' => ['avatar', 'cover', 'image', 'images', 'thumb', 'banner'],
            ],
        ],

        // App C (match by Package-Name)
        'com.demo.reader' => [
            'enabled' => true,
            'route_aliases' => [
                'm3e1' => ['path' => 'content/detail', 'method' => 'POST'],
                'k4h7' => ['path' => 'favorites/collect', 'method' => 'POST'],
                'q2l5' => ['path' => 'favorites/cancel', 'method' => 'POST'],
                'd8r6' => ['path' => 'user/withdrawal/records', 'method' => 'POST'],
            ],
            'request_key_map' => [
                'aid' => 'article_id',
                'cid' => 'content_id',
                'uid' => 'uuid',
                'tkv' => 'token',
            ],
            'response_key_map' => [
                'status' => 'ok',
                'msg' => 'tip',
                'data' => 'res',
                'code' => 'ec',
            ],
            'response_data_key_map' => [
                'detail' => 'ctx',
                'records' => 'items',
                'amount' => 'amt',
            ],
            'protocol' => [
                'encrypt_request' => true,
                'encrypt_response' => true,
                'allow_plaintext_request' => true,
                'payload_field' => 'packet',
                'sign_field' => 'digest',
                'timestamp_field' => 'stamp',
                'nonce_field' => 'salt',
                'version_field' => 'rev',
            ],
            'security' => [
                'timestamp_window_seconds' => 180,
                'nonce_ttl_seconds' => 180,
            ],
            'crypto' => [
                'cipher' => 'AES-256-CBC',
                'key' => env('READER_OBF_KEY'),
                'iv' => env('READER_OBF_IV'),
                'sign_key' => env('READER_OBF_SIGN_KEY'),
            ],
            'image_url' => [
                'enabled' => true,
                'domain' => env('READER_IMAGE_DOMAIN', 'https://img-reader.example.com'),
                'fields' => [],
            ],
        ],
    ],
];
