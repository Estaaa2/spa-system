<?php

return [
    'paths' => ['api/*', 'storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://127.0.0.1:5000',  // ← add your Flutter web origin explicitly
        'http://localhost:5000',
        '*',                       // keep * for other environments
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
