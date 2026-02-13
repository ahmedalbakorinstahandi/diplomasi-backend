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

    'geidea' => [
        'public_key' => env('GEIDEA_PUBLIC_KEY'),
        'api_password' => env('GEIDEA_API_PASSWORD'),
        'base_url' => env('GEIDEA_BASE_URL', 'https://api.merchant.geidea.net'),
        'hpp_script_url' => env('GEIDEA_HPP_SCRIPT_URL', 'https://www.merchant.geidea.net/hpp/geideaCheckout.min.js'),
        'callback_url' => env('GEIDEA_CALLBACK_URL'),
        'return_url' => env('GEIDEA_RETURN_URL'),
        'currency' => env('GEIDEA_CURRENCY', 'EGP'),
    ],

];
