<?php

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\KycTransaction;
use App\Models\Processor;
use App\Models\Tenant;

test('activity captures workflow id when registering kyc transaction', function () {
    // Create tenant
    $tenant = Tenant::factory()->create();
    app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($tenant);
    
    // Create eKYC processor
    $processor = Processor::factory()->create([
        'slug' => 'ekyc-verification',
        'name' => 'eKYC Verification',
        'category' => 'validation',
        'class_name' => \App\Processors\EKycVerificationProcessor::class,
    ]);

    $campaign = Campaign::factory()->create([
        'pipeline_config' => [
            'processors' => [
                ['id' => $processor->id, 'type' => 'validation', 'config' => ['country' => 'PH']],
            ],
        ],
    ]);

    $document = Document::factory()->create(['campaign_id' => $campaign->id]);
    $job = DocumentJob::factory()->create([
        'campaign_id' => $campaign->id,
        'document_id' => $document->id,
        'pipeline_instance' => $campaign->pipeline_config,
    ]);

    // Run document:process command which will actually execute the workflow
    $this->artisan('document:process', [
        'documents' => [$document->file_path],
        '--campaign' => $campaign->slug,
        '--tenant' => $tenant->slug,
        '--wait' => true,
    ]);

    // Check if KYC transaction was created with workflow_id
    $kycTransaction = KycTransaction::where('document_id', $document->id)->first();
    
    // This will tell us if the workflowId() method is working
    dump([
        'transaction_exists' => $kycTransaction !== null,
        'workflow_id' => $kycTransaction?->workflow_id,
        'document_job_id' => $kycTransaction?->document_job_id,
    ]);
    
    expect($kycTransaction)->not->toBeNull('KYC transaction should exist')
        ->and($kycTransaction->workflow_id)->not->toBeNull('Workflow ID should be captured')
        ->and($kycTransaction->document_job_id)->toBe($job->id);
})->skip('Requires real eKYC processor execution');
