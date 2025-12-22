<?php

return [
    'paths' => ['*'], 
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'https://ong-frontend-production.vercel.app',
    ],
    'allowed_origins_patterns' => ['/https:\/\/.*\.ngrok-free\.app/'],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
