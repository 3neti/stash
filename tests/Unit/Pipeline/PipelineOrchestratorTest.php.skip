<?php

use App\Data\Pipeline\ProcessorConfigData;
use App\Exceptions\ProcessorException;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\ProcessorExecution;
use App\Processors\AbstractProcessor;
use App\Services\Pipeline\PipelineOrchestrator;
use App\Services\Pipeline\ProcessorRegistry;
use Tests\DeadDropTestCase;

uses(DeadDropTestCase::class);

beforeEach(function () {
    $this->registry = app(ProcessorRegistry::class);
    $this->orchestrator = new PipelineOrchestrator($this->registry);
});

describe('Pipeline Orchestrator', function () {
    test('executes single processor successfully', function () {
        // Register a test processor
        $testProcessor = new class extends AbstractProcessor
        {
            protected string $name = 'TestProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['text' => 'extracted'];
            }
        };

        $this->registry->register('test_ocr', get_class($testProcessor));

        // Create test data
        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    [
                        'id' => 'test_ocr',
                        'type' => 'ocr',
                        'config' => [],
                    ],
                ],
            ],
        ]);

        // Execute pipeline
        $result = $this->orchestrator->executePipeline($documentJob);

        // Assertions
        expect($result)->toBeTrue();

        // Check ProcessorExecution was created
        $execution = ProcessorExecution::where('job_id', $documentJob->id)->first();
        expect($execution)->not()->toBeNull()
            ->and($execution->output_data)->toBe(['text' => 'extracted'])
            ->and($execution->error_message)->toBeNull();
    });

    test('executes multiple processors in sequence', function () {
        // Register test processors
        $processor1 = new class extends AbstractProcessor
        {
            protected string $name = 'Processor1';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['step1' => 'done'];
            }
        };

        $processor2 = new class extends AbstractProcessor
        {
            protected string $name = 'Processor2';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['step2' => 'done'];
            }
        };

        $this->registry->register('proc1', get_class($processor1));
        $this->registry->register('proc2', get_class($processor2));

        // Create test data
        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    ['id' => 'proc1', 'type' => 'test', 'config' => []],
                    ['id' => 'proc2', 'type' => 'test', 'config' => []],
                ],
            ],
        ]);

        // Execute
        $result = $this->orchestrator->executePipeline($documentJob);

        // Verify
        expect($result)->toBeTrue();

        $executions = ProcessorExecution::where('job_id', $documentJob->id)
            ->orderBy('created_at')
            ->get();

        expect($executions)->toHaveCount(2);
    });

    test('stops pipeline on processor failure', function () {
        // Register processors (second one will fail)
        $processor1 = new class extends AbstractProcessor
        {
            protected string $name = 'SuccessProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['success' => true];
            }
        };

        $processor2 = new class extends AbstractProcessor
        {
            protected string $name = 'FailProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                throw new Exception('Processing failed');
            }
        };

        $processor3 = new class extends AbstractProcessor
        {
            protected string $name = 'NeverRunsProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['never' => 'executed'];
            }
        };

        $this->registry->register('proc1', get_class($processor1));
        $this->registry->register('proc2', get_class($processor2));
        $this->registry->register('proc3', get_class($processor3));

        // Create test data
        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    ['id' => 'proc1', 'type' => 'test', 'config' => []],
                    ['id' => 'proc2', 'type' => 'test', 'config' => []],
                    ['id' => 'proc3', 'type' => 'test', 'config' => []],
                ],
            ],
        ]);

        // Execute
        $result = $this->orchestrator->executePipeline($documentJob);

        // Verify pipeline failed
        expect($result)->toBeFalse();

        // Only 2 executions should exist (third processor never ran)
        $executions = ProcessorExecution::where('job_id', $documentJob->id)->get();
        expect($executions)->toHaveCount(2)
            ->and($executions[1]->error_message)->toBe('Processing failed');
    });

    test('throws exception when processor cannot handle document', function () {
        // Register processor that cannot handle documents
        $processor = new class extends AbstractProcessor
        {
            protected string $name = 'SelectiveProcessor';

            public function canProcess(Document $document): bool
            {
                return false; // Cannot process any documents
            }

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return [];
            }
        };

        $this->registry->register('selective', get_class($processor));

        // Create test data
        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    ['id' => 'selective', 'type' => 'test', 'config' => []],
                ],
            ],
        ]);

        // Should throw exception
        expect(fn () => $this->orchestrator->executePipeline($documentJob))
            ->toThrow(ProcessorException::class);
    });

    test('records execution duration', function () {
        $processor = new class extends AbstractProcessor
        {
            protected string $name = 'SlowProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                usleep(10000); // Sleep for 10ms

                return ['done' => true];
            }
        };

        $this->registry->register('slow', get_class($processor));

        // Create test data
        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    ['id' => 'slow', 'type' => 'test', 'config' => []],
                ],
            ],
        ]);

        $this->orchestrator->executePipeline($documentJob);

        $execution = ProcessorExecution::where('job_id', $documentJob->id)->first();

        expect($execution->duration_ms)->toBeGreaterThanOrEqual(10)
            ->and($execution->completed_at)->not()->toBeNull();
    });
});
