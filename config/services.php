<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Login dengan Google)
    |--------------------------------------------------------------------------
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'tutor_redirect' => env('GOOGLE_TUTOR_REDIRECT_URI', env('GOOGLE_REDIRECT_URI')),
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'length' => env('OTP_LENGTH', 5),
        'expires_minutes' => env('OTP_EXPIRES_MINUTES', 5),
        'max_attempts' => env('OTP_MAX_ATTEMPTS', 5),
        'resend_seconds' => env('OTP_RESEND_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Fonnte Configuration
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'provider' => env('WHATSAPP_PROVIDER', 'fonnte'),
        'token' => env('WHATSAPP_API_TOKEN'),
        'api_url' => env('WHATSAPP_API_URL', 'https://api.fonnte.com/send'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],

    'xendit' => [
        'secret_key' => env('XENDIT_SECRET_KEY'),
        'callback_token' => env('XENDIT_CALLBACK_TOKEN'),
    ],

    'payment' => [
        'default_gateway' => env('PAYMENT_GATEWAY', 'midtrans'),
        'service_fee' => (int) env('PLATFORM_SERVICE_FEE', 2500),
    ],

    'webrtc' => [
        'stun_server' => env('WEBRTC_STUN_SERVER', 'stun:stun.l.google.com:19302'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (Push Notifications)
    |--------------------------------------------------------------------------
    */
    'firebase' => [
        'server_key' => env('FIREBASE_SERVER_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA (Pengajuan Tutor)
    |--------------------------------------------------------------------------
    */
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenRouter AI (Free & Unlimited Chat)
    |--------------------------------------------------------------------------
    */
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'mistral-7b-instruct'),
        'api_url' => 'https://openrouter.ai/api/v1/chat/completions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Groq AI (Free & Unlimited Chat)
    |--------------------------------------------------------------------------
    */
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'mixtral-8x7b-32768'),
        'api_url' => 'https://api.groq.com/v1/chat/completions',
    ],

];
