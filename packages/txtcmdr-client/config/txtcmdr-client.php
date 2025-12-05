<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | The default SMS driver to use. Currently supports:
    | - txtcmdr (default)
    | - twilio (future)
    |
    */
    'default_driver' => env('SMS_DRIVER', 'txtcmdr'),

    /*
    |--------------------------------------------------------------------------
    | Default txtcmdr API URL
    |--------------------------------------------------------------------------
    |
    | The default txtcmdr server URL. This can be overridden per-campaign
    | in the campaign's txtcmdr_config JSON column.
    |
    */
    'default_url' => env('TXTCMDR_API_URL', 'https://txtcmdr.example.com'),

    /*
    |--------------------------------------------------------------------------
    | txtcmdr API Token
    |--------------------------------------------------------------------------
    |
    | Global txtcmdr API token. Can be overridden per-campaign.
    |
    */
    'api_token' => env('TXTCMDR_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default Sender ID
    |--------------------------------------------------------------------------
    |
    | Default sender ID for SMS messages.
    |
    */
    'default_sender_id' => env('TXTCMDR_DEFAULT_SENDER_ID', 'STASH'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | HTTP request timeout in seconds for txtcmdr API calls.
    |
    */
    'timeout' => env('TXTCMDR_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for failed API requests.
    |
    */
    'retry' => [
        'network_errors' => [
            'attempts' => 3,
            'backoff_ms' => 1000, // Exponential backoff starting at 1s
        ],
        'server_errors' => [
            'attempts' => 2,
            'backoff_ms' => 2000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Verify SSL
    |--------------------------------------------------------------------------
    |
    | Whether to verify SSL certificates. Set to false only for local development.
    |
    */
    'verify_ssl' => env('TXTCMDR_VERIFY_SSL', true),
];
