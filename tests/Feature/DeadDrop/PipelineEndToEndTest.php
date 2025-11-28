<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\ProcessorExecution;
use App\Models\Tenant;
use App\Services\Pipeline\PipelineOrchestrator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LBHurtado\DeadDrop\Enums\Document\DocumentJobState\CompletedJobState;
use LBHurtado\DeadDrop\Enums\Document\DocumentJobState\PendingJobState;
use LBHurtado\DeadDrop\Enums\Document\DocumentJobState\RunningJobState;
use LBHurtado\DeadDrop\Enums\Document\DocumentState\CompletedState;
use LBHurtado\DeadDrop\Enums\Document\DocumentState\PendingState;
use LBHurtado\DeadDrop\Enums\Document\DocumentState\ProcessingState;
use LBHurtado\DeadDrop\Enums\ProcessorExecution\ProcessorExecutionState\CompletedExecutionState;
use LBHurtado\DeadDrop\Enums\ProcessorExecution\ProcessorExecutionState\PendingExecutionState;
use LBHurtado\DeadDrop\Enums\ProcessorExecution\ProcessorExecutionState\RunningExecutionState;

uses()->group('feature', 'pipeline', 'end-to-end');

beforeEach(function () {
    // Set up storage
    Storage::fake('local');

    // Create campaign with full pipeline configuration (tenant is set up by DeadDropTestCase)
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
                        ],
                        'model' => 'gpt-4o-mini',
                    ],
                ],
            ],
        ],
    ]);

    // Ensure processors exist in registry
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
});

test('processes document through complete pipeline: OCR → Classification → Extraction', function () {
    // Start the pipeline
    $job = $this->orchestrator->processDocument($this->document);

    // Verify DocumentJob created
    expect($job)->toBeInstanceOf(DocumentJob::class)
        ->and($job->document_id)->toBe($this->document->id)
        ->and($job->state)->toBeInstanceOf(CompletedJobState::class)
        ->and($job->pipeline_instance)->toBeArray()
        ->and($job->pipeline_instance['processors'])->toHaveCount(3);

    // Verify Document state transitioned
    $this->document->refresh();
    expect($this->document->state)->toBeInstanceOf(CompletedState::class);

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
    // Initial state
    expect($this->document->state)->toBeInstanceOf(PendingState::class);

    // Execute first processor (OCR)
    $job = DocumentJob::create([
        'document_id' => $this->document->id,
        'pipeline_instance' => $this->campaign->pipeline_config,
        'current_processor_index' => 0,
    ]);

    // Start processing
    $job->start();
    $this->document->toProcessing();

    expect($job->state)->toBeInstanceOf(RunningJobState::class)
        ->and($this->document->fresh()->state)->toBeInstanceOf(ProcessingState::class);

    // Execute pipeline
    $this->orchestrator->processDocument($this->document);

    // Final state
    expect($job->fresh()->state)->toBeInstanceOf(CompletedJobState::class)
        ->and($this->document->fresh()->state)->toBeInstanceOf(CompletedState::class);
});

test('processor executions track timing and token usage', function () {
    $job = $this->orchestrator->processDocument($this->document);

    $executions = $job->processorExecutions;

    foreach ($executions as $execution) {
        expect($execution->started_at)->not->toBeNull()
            ->and($execution->completed_at)->not->toBeNull()
            ->and($execution->duration_ms)->toBeGreaterThan(0)
            ->and($execution->completed_at->greaterThan($execution->started_at))->toBeTrue();

        // Classification and Extraction use tokens
        if (in_array($execution->processor_id, ['classifier', 'extractor'])) {
            expect($execution->tokens_used)->toBeGreaterThan(0);
        }
    }
});

test('pipeline handles processor failures gracefully', function () {
    // Create document with no file (will cause OCR to fail)
    $failDocument = Document::factory()->create([
        'campaign_id' => $this->campaign->id,
        'storage_path' => 'nonexistent/file.png',
        'storage_disk' => 'local',
        'mime_type' => 'image/png',
    ]);

    $job = $this->orchestrator->processDocument($failDocument);

    // Verify job failed
    $job->refresh();
    expect($job->state)->toBeInstanceOf(\LBHurtado\DeadDrop\Enums\Document\DocumentJobState\FailedJobState::class)
        ->and($job->error_log)->not->toBeEmpty()
        ->and($job->error_log)->toContain('not found');

    // Verify first processor execution failed
    $execution = $job->processorExecutions->first();
    expect($execution->state)->toBeInstanceOf(\LBHurtado\DeadDrop\Enums\ProcessorExecution\ProcessorExecutionState\FailedExecutionState::class)
        ->and($execution->error_message)->not->toBeEmpty();

    // Verify document marked as failed
    $failDocument->refresh();
    expect($failDocument->state)->toBeInstanceOf(\LBHurtado\DeadDrop\Enums\Document\DocumentState\FailedState::class);
});

test('pipeline stops after processor failure and does not execute subsequent processors', function () {
    // Create document with no file
    $failDocument = Document::factory()->create([
        'campaign_id' => $this->campaign->id,
        'storage_path' => 'nonexistent/file.png',
        'storage_disk' => 'local',
        'mime_type' => 'image/png',
    ]);

    $job = $this->orchestrator->processDocument($failDocument);

    // Only OCR execution should exist (failed)
    $executions = $job->processorExecutions;
    expect($executions)->toHaveCount(1)
        ->and($executions->first()->processor_id)->toBe('ocr')
        ->and($executions->first()->state)->toBeInstanceOf(\LBHurtado\DeadDrop\Enums\ProcessorExecution\ProcessorExecutionState\FailedExecutionState::class);

    // Classification and Extraction should NOT have been attempted
    expect($job->current_processor_index)->toBe(0);
});

test('metadata accumulates through pipeline stages', function () {
    $job = $this->orchestrator->processDocument($this->document);

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
    $job = $this->orchestrator->processDocument($this->document);

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
    $job = $this->orchestrator->processDocument($this->document);

    $executions = $job->processorExecutions()->orderBy('id')->get();

    expect($executions[0]->processor_id)->toBe('ocr')
        ->and($executions[1]->processor_id)->toBe('classifier')
        ->and($executions[2]->processor_id)->toBe('extractor');
});

test('pipeline creates processor records on-the-fly if missing', function () {
    // Count processors before
    $beforeCount = Processor::count();

    // Execute pipeline (processors should exist from beforeEach)
    $job = $this->orchestrator->processDocument($this->document);

    // Count processors after
    $afterCount = Processor::count();

    // Should not have created duplicates
    expect($afterCount)->toBe($beforeCount);

    // Verify all 3 processor types exist
    expect(Processor::where('slug', 'ocr')->exists())->toBeTrue()
        ->and(Processor::where('slug', 'classifier')->exists())->toBeTrue()
        ->and(Processor::where('slug', 'extractor')->exists())->toBeTrue();
});
