<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    | SMS gateway used for OTP messages. Supported drivers: "log" (writes the
    | message to the log, for local development), "taqnyat" and "unifonic".
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        'taqnyat' => [
            'token' => env('TAQNYAT_TOKEN'),
            'sender' => env('TAQNYAT_SENDER'),
        ],

        'unifonic' => [
            'app_sid' => env('UNIFONIC_APP_SID'),
            'sender' => env('UNIFONIC_SENDER'),
        ],
    ],

    /*
    | Payment gateway used when the customer accepts an offer. Supported drivers:
    | "fake" (every payment succeeds, for local / staging) and "moyasar" (live).
    */
    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),

        'moyasar' => [
            'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
            'secret_key' => env('MOYASAR_SECRET_KEY'),
            'apple_pay_merchant_id' => env('MOYASAR_APPLE_PAY_MERCHANT_ID'),
        ],
    ],

    'google' => [
        // Comma separated OAuth client IDs (Android, iOS, Web) accepted as the ID token audience.
        'client_ids' => array_filter(explode(',', (string) env('GOOGLE_CLIENT_IDS', ''))),
    ],

    'apple' => [
        // Comma separated iOS bundle ID / Services ID accepted as the identity token audience.
        'client_ids' => array_filter(explode(',', (string) env('APPLE_CLIENT_IDS', ''))),
    ],

];
