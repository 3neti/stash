<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laravel Workflow Integration
    |--------------------------------------------------------------------------
    |
    | Enable Laravel Workflow for document processing pipelines.
    | When false, uses legacy ProcessDocumentJob.
    |
    */
    'use_laravel_workflow' => env('USE_LARAVEL_WORKFLOW', true),
];
