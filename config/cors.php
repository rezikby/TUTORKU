<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

  'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:5173'),
    'https://rezi.yopaaa.xyz',
    'http://rezi.yopaaa.xyz',
],


    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];