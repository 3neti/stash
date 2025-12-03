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
     * Webhook configuration
     */
    'webhook' => [
        'secret' => env('HYPERVERGE_WEBHOOK_SECRET'),
        'process_webhook_job' => \App\Jobs\ProcessHypervergeWebhook::class,
    ],
];
