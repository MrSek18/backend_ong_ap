<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://ong-frontend-production.vercel.app',
        'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [
        '/https:\/\/.*\.ngrok-free\.app/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
