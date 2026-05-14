<?php

return [
    'paths' => [
        'api/*',
        'broadcasting/auth',
        'sanctum/csrf-cookie',
        '_boost/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://10.144.155.183',
        'http://10.144.155.183:8000',
        'http://10.144.155.183:5173',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:5173',
        'http://localhost',
        'http://localhost:5173',
        'http://192.168.1.9',
        'http://192.168.1.9:5173',
    ],

    'allowed_origins_patterns' => [
        '/^http:\/\/10\.144\.155\.183(:\d+)?$/',
        '/^http:\/\/127\.0\.0\.1(:\d+)?$/',
        '/^http:\/\/localhost(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-CSRF-TOKEN',
        'Authorization',
    ],

    'max_age' => 7200,

    'supports_credentials' => true,
];
