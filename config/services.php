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
    /*
     | PayMongo. PAYMONGO_MODE picks which keys every checkout uses
     | (subscriptions and reservations). Read through App\Support\PayMongo,
     | never env(), so `php artisan config:cache` keeps working.
     |
     | Each mode has its own webhook (registered with that mode's key) and so
     | its own signing secret. The old PAYMONGO_SECRET_KEY and
     | PAYMONGO_WEBHOOK_SECRET still work as the test values.
     */
    'paymongo' => [
        'mode' => env('PAYMONGO_MODE', 'test'),

        'test_secret_key' => env('PAYMONGO_TEST_SECRET_KEY', env('PAYMONGO_SECRET_KEY')),
        'test_webhook_secret' => env('PAYMONGO_TEST_WEBHOOK_SECRET', env('PAYMONGO_WEBHOOK_SECRET')),

        'live_secret_key' => env('PAYMONGO_LIVE_SECRET_KEY'),
        'live_webhook_secret' => env('PAYMONGO_LIVE_WEBHOOK_SECRET'),

        // Methods offered in live mode, comma-separated. An Individual
        // PayMongo account can only accept QR Ph; a registered business can
        // add gcash, paymaya, card, grab_pay once PayMongo approves them.
        'live_payment_methods' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('PAYMONGO_LIVE_METHODS', 'qrph'))
        ))),
    ],

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

];
