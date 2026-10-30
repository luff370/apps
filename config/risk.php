<?php

return [
    // Explicit asynchronous connection: never inherit QUEUE_CONNECTION=sync.
    'queue_connection' => env('RISK_QUEUE_CONNECTION', 'risk'),
    'queue' => env('RISK_QUEUE', 'risk'),
];
