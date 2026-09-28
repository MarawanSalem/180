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
    |--------------------------------------------------------------------------
    | Mock Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Controls the tri-state simulation probabilities for MockPaymentProvider.
    | The three rates should sum to 100. Any remainder after success + failure
    | is automatically treated as timeout.
    |
    */
    'mock_gateway' => [
        'success_rate' => (int) env('MOCK_GATEWAY_SUCCESS_RATE', 80),
        'failure_rate' => (int) env('MOCK_GATEWAY_FAILURE_RATE', 10),
        // timeout_rate is implicitly 100 - success_rate - failure_rate (default: 10)
    ],

];
