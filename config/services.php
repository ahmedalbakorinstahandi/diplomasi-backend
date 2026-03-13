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

    'moyasar' => [
        'base_url' => env('MOYASAR_BASE_URL', 'https://api.moyasar.com/v1'),
        'public_key' => env('MOYASAR_PUBLIC_KEY'),
        'secret_key' => env('MOYASAR_SECRET_KEY'),
        'webhook_secret_token' => env('MOYASAR_WEBHOOK_SECRET_TOKEN'),
        'currency' => env('MOYASAR_CURRENCY', 'SAR'),
        'webhook_events' => array_values(array_filter(array_map(
            static fn ($event) => trim($event),
            explode(',', (string) env('MOYASAR_WEBHOOK_EVENTS', 'payment_paid,payment_failed,payment_voided,payment_authorized,payment_captured,payment_refunded,payment_abandoned,payment_verified'))
        ))),
        'mode' => env('MOYASAR_MODE', 'test'),
    ],

    'billing' => [
        'renewal_grace_period_minutes' => (int) env('BILLING_RENEWAL_GRACE_MINUTES', 15),
    ],

    'apple' => [
        'shared_secret' => env('APPLE_IAP_SHARED_SECRET'),
    ],

    'translation' => [
        // Supported providers: mymemory, libretranslate
        'provider' => env('TRANSLATION_PROVIDER', 'mymemory'),
        'base_url' => env('TRANSLATION_BASE_URL', 'https://api.mymemory.translated.net/get'),
        'api_key' => env('TRANSLATION_API_KEY'),
        'timeout' => (int) env('TRANSLATION_TIMEOUT', 8),
    ],

];
