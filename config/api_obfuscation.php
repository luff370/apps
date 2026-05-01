<?php

return [
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
        // Example profile
        // '10001' => [
        //     'enabled' => true,
        //     'route_aliases' => [
        //         'k9s2' => ['path' => 'auth/login_by_uuid', 'method' => 'POST'],
        //     ],
        //     'request_key_map' => [
        //         'device_token' => 'token',
        //     ],
        //     'response_key_map' => [
        //         'status' => 's',
        //         'msg' => 'm',
        //         'data' => 'payload',
        //     ],
        //     'response_data_key_map' => [
        //         'token' => 'session_code',
        //     ],
        // ],
        'default' => [
            'enabled' => false,
            'route_aliases' => [],
            'request_key_map' => [],
            'response_key_map' => [],
            'response_data_key_map' => [],
        ],
    ],
];
