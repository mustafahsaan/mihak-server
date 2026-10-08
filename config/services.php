<?php

// تُدمج مع إعدادات الخدمات الافتراضية في Laravel.
return [
    'zaincash' => [
        'base_url' => env('ZAINCASH_BASE_URL', 'https://pg-api-uat.zaincash.iq'),
        'client_id' => env('ZAINCASH_CLIENT_ID'),
        'client_secret' => env('ZAINCASH_CLIENT_SECRET'),
        'api_key' => env('ZAINCASH_API_KEY'),
        'service_type' => env('ZAINCASH_SERVICE_TYPE', 'Testing service'),
        'language' => env('ZAINCASH_LANGUAGE', 'ar'),
    ],

    'qicard' => [
        'base_url' => env('QICARD_BASE_URL', 'https://uat-sandbox-3ds-api.qi.iq/api/v1'),
        'terminal_id' => env('QICARD_TERMINAL_ID'),
        'username' => env('QICARD_USERNAME'),
        'password' => env('QICARD_PASSWORD'),
        'locale' => env('QICARD_LOCALE', 'en_US'),
        'public_key_path' => env('QICARD_PUBLIC_KEY_PATH', 'storage/app/private/qicard-public.pem'),
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],
];
