<?php

return [
    /**
     * HyperVerge API Base URL
     */
    'base_url' => env('HYPERVERGE_BASE_URL', 'https://ind.idv.hyperverge.co/v1'),

    /**
     * HyperVerge App ID (from your dashboard)
     */
    'app_id' => env('HYPERVERGE_APP_ID'),

    /**
     * HyperVerge App Key (from your dashboard)
     */
    'app_key' => env('HYPERVERGE_APP_KEY'),

    /**
     * Default workflow ID for Link KYC
     */
    'url_workflow' => env('HYPERVERGE_URL_WORKFLOW', 'workflow_2nQDNT'),

    /**
     * HTTP request timeout in seconds
     */
    'timeout' => env('HYPERVERGE_TIMEOUT', 30),

    /**
     * Test mode - returns mock data instead of calling API
     */
    'test_mode' => env('HYPERVERGE_TEST_MODE', false),

    /**
     * Fixed transaction IDs for testing (comma-separated)
     * When set, the eKYC processor will use these IDs instead of generating new ones.
     * Useful for testing with existing HyperVerge transactions.
     * Example: EKYC-1764773764-3863,EKYC-1234567890-5678
     */
    'fixed_transaction_ids' => env('HYPERVERGE_FIXED_TRANSACTION_IDS') 
        ? array_map('trim', explode(',', env('HYPERVERGE_FIXED_TRANSACTION_IDS')))
        : [],

    /**
     * Webhook configuration
     */
    'webhook' => [
        'secret' => env('HYPERVERGE_WEBHOOK_SECRET'),
        'process_webhook_job' => \App\Jobs\ProcessHypervergeWebhook::class,
    ],
];
