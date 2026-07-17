<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
        'is_3ds' => env('MIDTRANS_IS_3DS', true),
        'mock' => env('MIDTRANS_MOCK', false), // test isolation — skip real API calls
    ],

    'biteship' => [
        'api_key' => env('BITESHIP_API_KEY'),
        'is_production' => env('BITESHIP_IS_PRODUCTION', false),
        'base_url' => env('BITESHIP_BASE_URL', 'https://api.biteship.com'),
        'mock' => env('BITESHIP_MOCK', false), // test isolation — skip real API calls
        'webhook_ips' => env('BITESHIP_WEBHOOK_IPS', ''), // pontail: comma-separated IPs
        // store origin for rates & order creation
        'origin_postal_code' => env('BITESHIP_ORIGIN_POSTAL_CODE'),
        'origin_address' => env('BITESHIP_ORIGIN_ADDRESS'),
        'origin_contact_name' => env('BITESHIP_ORIGIN_CONTACT_NAME', 'AlbaSambosa'),
        'origin_contact_phone' => env('BITESHIP_ORIGIN_CONTACT_PHONE'),
        'origin_contact_email' => env('BITESHIP_ORIGIN_CONTACT_EMAIL'),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'store_address' => env('TWILIO_STORE_ADDRESS', 'Jl. G No.120, RT.8/RW.6, Srengseng, Kec. Kembangan, Jakarta Barat 11630'),
        'store_hours' => env('TWILIO_STORE_HOURS', '09.00 - 20.00 WIB'),
    ],

];
