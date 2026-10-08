<?php

return [

    'default' => env('AI_CHANNEL', 'baidu'),

    'channels' => [
        'baidu' => [
            'app_key'=>'Of1hI84mQJvtcLuj6ieJKCrh',
            'app_secret'=>'Vfa3exiE9oUOiaEe7Is6VMotTowaAYxp'
        ],
        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY', ''),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'timeout' => (int) env('DEEPSEEK_TIMEOUT', 60),
            'temperature' => (float) env('DEEPSEEK_TEMPERATURE', 0.3),
            'max_tokens' => (int) env('DEEPSEEK_MAX_TOKENS', 4096),
        ],
    ],

];
