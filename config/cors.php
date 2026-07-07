<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique([
        env('FRONTEND_URL', 'https://rezi.nlabs.id'),
        'https://rezi.nlabs.id',
        'http://rezi.nlabs.id',
        'https://rezi-laravel.nlabs.id',
        'http://rezi-laravel.nlabs.id',
    ])),


    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];