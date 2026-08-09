<?php

return [
    'paths' => ['api/*', 'storage/*', 'storage/branch_profiles/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://127.0.0.1:5000',
        'http://localhost:5000',
        'http://10.0.2.2:5000',
        'http://127.0.0.1:8000',  // Add this for testing
        'http://localhost:8000',
        '*',   // Add this for testing
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,  // Keep as false since you're using '*'
];
