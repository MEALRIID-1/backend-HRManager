<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // ❌ '*' interdit avec supports_credentials = true
    'allowed_origins' => [
        'http://localhost:3000',  // Next.js dev
        'http://localhost:3001',  // si autre port
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];