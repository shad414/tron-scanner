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

    'tron' => [
        // Optional: raises TronGrid rate limits (https://www.trongrid.io)
        'trongrid_key' => env('TRONGRID_API_KEY'),
        // Needed for wallet/exchange labels and Tronscan risk flags (https://tronscan.org/#/myaccount/apiKeys)
        'tronscan_key' => env('TRONSCAN_API_KEY'),
        // Max TRC20 transfers to analyze per scan (200 per page)
        'max_transfers' => env('TRON_MAX_TRANSFERS', 1000),
        // When set, the whole site asks for this password
        'password' => env('SCANNER_PASSWORD'),
    ],

    'telegram' => [
        // Create a bot with @BotFather and paste its token here
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
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
