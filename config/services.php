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
    | Payment gateways used when the customer accepts an offer. Drivers:
    | "fake" (every payment succeeds, for local / staging) and "live" (real gateways:
    | Moyasar for card / Apple Pay, Tabby and Tamara for "buy now, pay later").
    | "moyasar" is the old name of "live" and still works.
    */
    'payment' => [
        'driver' => env('PAYMENT_DRIVER', 'fake'),

        'moyasar' => [
            'publishable_key' => env('MOYASAR_PUBLISHABLE_KEY'),
            'secret_key' => env('MOYASAR_SECRET_KEY'),
            'apple_pay_merchant_id' => env('MOYASAR_APPLE_PAY_MERCHANT_ID'),
        ],

        // Tabby (tabby.ai). Test keys work on the same URL.
        'tabby' => [
            'secret_key' => env('TABBY_SECRET_KEY'),
            'merchant_code' => env('TABBY_MERCHANT_CODE'),
            'base_url' => env('TABBY_BASE_URL', 'https://api.tabby.ai'),
        ],

        // Tamara (tamara.co). Live URL: https://api.tamara.co
        'tamara' => [
            'api_token' => env('TAMARA_API_TOKEN'),
            'base_url' => env('TAMARA_BASE_URL', 'https://api-sandbox.tamara.co'),
        ],

        // Tabby / Tamara send the customer back to these pages; the app's WebView watches for them.
        'redirect_urls' => [
            'success' => env('PAYMENT_SUCCESS_URL', env('APP_URL').'/payments/success'),
            'cancel' => env('PAYMENT_CANCEL_URL', env('APP_URL').'/payments/cancel'),
            'failure' => env('PAYMENT_FAILURE_URL', env('APP_URL').'/payments/failure'),
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
