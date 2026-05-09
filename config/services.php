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

    'vk' => [
        'bot_token' => env('VK_BOT_TOKEN'),
        'group_id' => env('VK_GROUP_ID'),
        'api_version' => env('VK_API_VERSION', '5.199'),
        'long_poll_wait' => env('VK_LONG_POLL_WAIT', 25),
    ],

    'crawl' => [
        'interval_minutes' => env('CRAWL_INTERVAL_MINUTES', 15),
    ],

    'ozon' => [
        'profile_path' => env('OZON_PROFILE_PATH', storage_path('app/ozon-browser-profile')),
        'chrome_path' => env('CHROME_PATH', '/usr/bin/chromium'),
    ],

];
