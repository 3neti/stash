<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\Tenant;
use App\Services\Pipeline\PipelineOrchestrator;
use App\States\Document\CompletedDocumentState;
use App\States\Document\PendingDocumentState;
use App\States\DocumentJob\CompletedJobState;
use App\States\ProcessorExecution\CompletedExecutionState;
use App\States\ProcessorExecution\FailedExecutionState;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesDashboardSetup;

uses(UsesDashboardSetup::class)
    ->group('feature', 'pipeline', 'end-to-end');

// TODO: Mock AI processors (OpenAI API integration requires real API calls)
// TODO: Fix fixture image paths in test setup

beforeEach(function () {
    // Set up storage
    Storage::fake('local');

    // Setup tenant with unique slug
    [$tenant, $user] = $this->setupDashboardTestTenant();
    $this->tenant = $tenant;

    // Create campaign with full pipeline configuration within tenant context
    TenantContext::run($this->tenant, function () {
        $this->campaign = Campaign::factory()->create([
        'name' => 'Invoice Processing Campaign',
        'pipeline_config' => [
            'processors' => [
                [
                    'id' => 'ocr',
                    'type' => 'ocr',
                    'config' => [
                        'language' => 'eng',
                        'psm' => 3,
                    ],
                ],
                [
                    'id' => 'classifier',
                    'type' => 'classification',
                    'config' => [
                        'categories' => ['invoice', 'receipt', 'contract', 'other'],
                        'model' => 'gpt-4o-mini',
                    ],
                ],
                [
                    'id' => 'extractor',
                    'type' => 'extraction',
                    'config' => [
                        'schema' => [
                            'invoice' => ['invoice_number', 'date', 'vendor', 'total_amount'],
                            'receipt' => ['merchant', 'date', 'total'],
                            'other' => ['title', 'date', 'description'],
                            'contract' => ['parties', 'date', 'terms'],
                        ],
                        'model' => 'gpt-4o-mini',
                    ],
                ],
            ],
        ],
        ]);

        // Ensure processors exist in database
        Processor::firstOrCreate(
            ['slug' => 'ocr'],
            ['name' => 'Tesseract OCR', 'category' => 'ocr', 'class_name' => 'App\Processors\OcrProcessor']
        );
        Processor::firstOrCreate(
            ['slug' => 'classifier'],
            ['name' => 'OpenAI Classification', 'category' => 'classification', 'class_name' => 'App\Processors\ClassificationProcessor']
        );
        Processor::firstOrCreate(
            ['slug' => 'extractor'],
            ['name' => 'OpenAI Extraction', 'category' => 'extraction', 'class_name' => 'App\Processors\ExtractionProcessor']
        );

        // Copy test image to storage
        $testImagePath = base_path('tests/Fixtures/images/test-document.png');
        $storagePath = 'documents/test-invoice.png';

        Storage::disk('local')->put(
            $storagePath,
            file_get_contents($testImagePath)
        );

        // Create document
        $this->document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
            'original_filename' => 'test-invoice.png',
            'mime_type' => 'image/png',
            'storage_path' => $storagePath,
            'storage_disk' => 'local',
            'size_bytes' => filesize($testImagePath),
            'metadata' => [],
        ]);

        $this->orchestrator = app(PipelineOrchestrator::class);

        // Register processors with ProcessorRegistry (must be done after orchestrator is created)
        $registry = app(\App\Services\Pipeline\ProcessorRegistry::class);
        $registry->register('ocr', \App\Processors\OcrProcessor::class);
        $registry->register('classifier', \App\Processors\ClassificationProcessor::class);
        $registry->register('extractor', \App\Processors\ExtractionProcessor::class);
    });
});

test('processes document through complete pipeline: OCR → Classification → Extraction', function () {
    test()->markTestSkipped('Pending: Mock AI processors - requires OpenAI API integration');
    
    // Start the pipeline within tenant context
    $job = TenantContext::run($this->tenant, fn() => 
        $this->orchestrator->processDocument($this->document)
    );

    // Verify DocumentJob created
    expect($job)->toBeInstanceOf(DocumentJob::class)
        ->and($job->document_id)->toBe($this->document->id);

    // Debug: Show error if failed
    if ($job->isFailed()) {
        dump('Job failed!', $job->error_log);
        $executions = $job->processorExecutions;
        foreach ($executions as $ex) {
            dump("Processor: {$ex->processor_id}", 'State: '.get_class($ex->state), 'Error: '.$ex->error_message);
        }
    }

    expect($job->state)->toBeInstanceOf(CompletedJobState::class)
        ->and($job->pipeline_instance)->toBeArray()
        ->and($job->pipeline_instance['processors'])->toHaveCount(3);

    // Verify Document state transitioned
    $this->document->refresh();
    expect($this->document->state)->toBeInstanceOf(CompletedDocumentState::class);

    // Verify all 3 ProcessorExecutions were created
    $executions = $job->processorExecutions()->orderBy('id')->get();
    expect($executions)->toHaveCount(3);

    // Verify OCR execution
    $ocrExecution = $executions[0];
    expect($ocrExecution->state)->toBeInstanceOf(CompletedExecutionState::class)
        ->and($ocrExecution->output_data)->toHaveKey('text')
        ->and($ocrExecution->output_data['text'])->toContain('Hello')
        ->and($ocrExecution->duration_ms)->toBeGreaterThan(0);

    // Verify Classification execution
    $classificationExecution = $executions[1];
    expect($classificationExecution->state)->toBeInstanceOf(CompletedExecutionState::class)
        ->and($classificationExecution->output_data)->toHaveKey('category')
        ->and($classificationExecution->output_data)->toHaveKey('confidence')
        ->and($classificationExecution->duration_ms)->toBeGreaterThan(0);

    // Verify Extraction execution
    $extractionExecution = $executions[2];
    expect($extractionExecution->state)->toBeInstanceOf(CompletedExecutionState::class)
        ->and($extractionExecution->output_data)->toHaveKey('fields')
        ->and($extractionExecution->output_data)->toHaveKey('category')
        ->and($extractionExecution->duration_ms)->toBeGreaterThan(0);

    // Verify metadata flow through pipeline
    $this->document->refresh();
    expect($this->document->metadata)->toHaveKey('extracted_text')
        ->and($this->document->metadata)->toHaveKey('category')
        ->and($this->document->metadata)->toHaveKey('extracted_fields');
});

test('document state transitions correctly during pipeline execution', function () {
    test()->markTestSkipped('Requires AI processor mocking');
    
    // Initial state
    expect($this->document->state)->toBeInstanceOf(PendingDocumentState::class);

    // Execute pipeline within tenant context
    $job = TenantContext::run($this->tenant, fn() => 
        $this->orchestrator->processDocument($this->document)
    );

    // Verify state transitions occurred
    expect($job->state)->toBeInstanceOf(CompletedJobState::class)
        ->and($this->document->fresh()->state)->toBeInstanceOf(CompletedDocumentState::class);
});

test('processor executions track timing and token usage', function () {
    test()->markTestSkipped('Requires AI processor mocking');
    
    $job = TenantContext::run($this->tenant, fn() =>
        $this->orchestrator->processDocument($this->document)
    );

    $executions = $job->processorExecutions;

    foreach ($executions as $execution) {
        expect($execution->started_at)->not->toBeNull()
            ->and($execution->completed_at)->not->toBeNull()
            ->and($execution->duration_ms)->toBeGreaterThan(0)
            ->and($execution->completed_at->greaterThanOrEqualTo($execution->started_at))->toBeTrue();

        // Classification and Extraction use tokens
        $processor = $execution->processor;
        if (in_array($processor->slug, ['classifier', 'extractor'])) {
            expect($execution->tokens_used)->toBeGreaterThan(0);
        }
    }
});

test('pipeline handles processor failures gracefully', function () {
    // Create document with no file within tenant context
    $failDocument = TenantContext::run($this->tenant, function () {
        return Document::factory()->create([
            'campaign_id' => $this->campaign->id,
            'storage_path' => 'nonexistent/file.png',
            'storage_disk' => 'local',
            'mime_type' => 'image/png',
        ]);
    });

    $job = TenantContext::run($this->tenant, fn() => 
        $this->orchestrator->processDocument($failDocument)
    );

    // Verify job failed
    $job->refresh();
    expect($job->state)->toBeInstanceOf(\App\States\DocumentJob\FailedJobState::class)
        ->and($job->error_log)->not->toBeEmpty();

    // Verify first processor execution failed
    $execution = $job->processorExecutions->first();
    expect($execution->state)->toBeInstanceOf(FailedExecutionState::class)
        ->and($execution->error_message)->not->toBeEmpty();

    // Verify document marked as failed
    $failDocument->refresh();
    expect($failDocument->state)->toBeInstanceOf(\App\States\Document\FailedDocumentState::class);
});

test('pipeline stops after processor failure and does not execute subsequent processors', function () {
    // Create document with no file within tenant context
    $failDocument = TenantContext::run($this->tenant, function () {
        return Document::factory()->create([
            'campaign_id' => $this->campaign->id,
            'storage_path' => 'nonexistent/file.png',
            'storage_disk' => 'local',
            'mime_type' => 'image/png',
        ]);
    });

    $job = TenantContext::run($this->tenant, fn() => 
        $this->orchestrator->processDocument($failDocument)
    );

    // Only OCR execution should exist (failed)
    $executions = $job->processorExecutions;
    $ocrProcessor = Processor::where('slug', 'ocr')->first();
    expect($executions)->toHaveCount(1)
        ->and($executions->first()->processor_id)->toBe($ocrProcessor->id)
        ->and($executions->first()->state)->toBeInstanceOf(FailedExecutionState::class);

    // Classification and Extraction should NOT have been attempted
    expect($job->current_processor_index)->toBe(0);
});

test('metadata accumulates through pipeline stages', function () {
    test()->markTestSkipped('Requires AI processor mocking');
    
    $job = TenantContext::run($this->tenant, fn() =>
        $this->orchestrator->processDocument($this->document)
    );

    $this->document->refresh();

    // After OCR: should have extracted_text
    expect($this->document->metadata)->toHaveKey('extracted_text');
    $extractedText = $this->document->metadata['extracted_text'];

    // After Classification: should have category
    expect($this->document->metadata)->toHaveKey('category');
    $category = $this->document->metadata['category'];

    // After Extraction: should have extracted_fields
    expect($this->document->metadata)->toHaveKey('extracted_fields');
    $fields = $this->document->metadata['extracted_fields'];

    // Verify data flows correctly
    expect($extractedText)->toBeString()
        ->and($category)->toBeString()
        ->and($fields)->toBeArray();
});

test('pipeline tracks processor count and completion percentage', function () {
    test()->markTestSkipped('Requires AI processor mocking');
    
    $job = TenantContext::run($this->tenant, fn() =>
        $this->orchestrator->processDocument($this->document)
    );

    expect($job->pipeline_instance['processors'])->toHaveCount(3)
        ->and($job->current_processor_index)->toBe(2) // 0-indexed, so 2 = 3rd processor
        ->and($job->processorExecutions)->toHaveCount(3);

    // All processors completed
    $completedCount = $job->processorExecutions()
        ->whereState('state', CompletedExecutionState::class)
        ->count();

    expect($completedCount)->toBe(3);
});

test('each processor execution has unique processor_id from config', function () {
    test()->markTestSkipped('Requires AI processor mocking');
    
    $job = TenantContext::run($this->tenant, fn() =>
        $this->orchestrator->processDocument($this->document)
    );

    $executions = $job->processorExecutions()->orderBy('id')->get();

    // Get processor IDs
    $ocrProcessor = Processor::where('slug', 'ocr')->first();
    $classifierProcessor = Processor::where('slug', 'classifier')->first();
    $extractorProcessor = Processor::where('slug', 'extractor')->first();

    expect($executions[0]->processor_id)->toBe($ocrProcessor->id)
        ->and($executions[1]->processor_id)->toBe($classifierProcessor->id)
        ->and($executions[2]->processor_id)->toBe($extractorProcessor->id);
});

test('pipeline creates processor records on-the-fly if missing', function () {
    // Count processors before
    $beforeCount = TenantContext::run($this->tenant, fn() => Processor::count());

    // Execute pipeline within tenant context
    $job = TenantContext::run($this->tenant, fn() => 
        $this->orchestrator->processDocument($this->document)
    );

    // Count processors after
    $afterCount = Processor::count();

    // Should not have created duplicates
    expect($afterCount)->toBe($beforeCount);

    // Verify all 3 processor types exist
    expect(Processor::where('slug', 'ocr')->exists())->toBeTrue()
        ->and(Processor::where('slug', 'classifier')->exists())->toBeTrue()
        ->and(Processor::where('slug', 'extractor')->exists())->toBeTrue();
});
