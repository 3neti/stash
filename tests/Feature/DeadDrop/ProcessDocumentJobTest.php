<?php

use App\Data\Pipeline\ProcessorConfigData;
use App\Jobs\Pipeline\ProcessDocumentJob;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\ProcessorExecution;
use App\Processors\AbstractProcessor;
use App\Services\Pipeline\ProcessorRegistry;
use Illuminate\Support\Facades\Queue;

describe('Process Document Job', function () {

    test('job can be dispatched to queue', function () {
        Queue::fake();

        // Register test processor
        $testProcessor = new class extends AbstractProcessor
        {
            protected string $name = 'TestProcessor';

            protected string $category = 'test';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['status' => 'processed', 'test' => true];
            }
        };
        app(ProcessorRegistry::class)->register('test_processor', get_class($testProcessor));

        $campaign = Campaign::factory()->create([
            'pipeline_config' => [
                'processors' => [
                    [
                        'id' => 'test_processor',
                        'type' => 'test',
                        'config' => [],
                    ],
                ],
            ],
        ]);

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    [
                        'id' => 'test_processor',
                        'type' => 'test',
                        'config' => [],
                    ],
                ],
            ],
        ]);

        // Dispatch the job
        ProcessDocumentJob::dispatch($documentJob->id);

        // Assert job was pushed
        Queue::assertPushed(ProcessDocumentJob::class, function ($job) use ($documentJob) {
            return $job->documentJobId === $documentJob->id;
        });
    });

    test('job executes pipeline successfully', function () {
        // Register test processor and bind to container
        $testProcessor = new class extends AbstractProcessor
        {
            protected string $name = 'TestProcessor';

            protected string $category = 'test';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['status' => 'processed', 'test' => true];
            }
        };

        $registry = app(ProcessorRegistry::class);
        $registry->register('test_processor', get_class($testProcessor));
        $this->app->instance(ProcessorRegistry::class, $registry);

        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    [
                        'id' => 'test_processor',
                        'type' => 'test',
                        'config' => [],
                    ],
                ],
            ],
        ]);

        // Execute job synchronously
        $job = new ProcessDocumentJob($documentJob->id);
        $job->handle(app(\App\Services\Pipeline\PipelineOrchestrator::class));

        // Refresh models
        $documentJob->refresh();
        $document->refresh();

        // Assert job completed
        expect($documentJob->isCompleted())->toBeTrue()
            ->and($document->isCompleted())->toBeTrue();

        // Assert processor execution was recorded
        $execution = ProcessorExecution::where('job_id', $documentJob->id)->first();
        expect($execution)->not()->toBeNull()
            ->and($execution->isCompleted())->toBeTrue()
            ->and($execution->output_data)->toBe(['status' => 'processed', 'test' => true]);
    });

    test('job handles processor failure with retry', function () {
        // Register a failing processor
        $failingProcessor = new class extends AbstractProcessor
        {
            protected string $name = 'FailingProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                throw new \Exception('Processor failed intentionally');
            }
        };

        app(ProcessorRegistry::class)->register('failing_processor', get_class($failingProcessor));

        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'max_attempts' => 3,
            'pipeline_instance' => [
                'processors' => [
                    [
                        'id' => 'failing_processor',
                        'type' => 'test',
                        'config' => [],
                    ],
                ],
            ],
        ]);

        // Execute job and expect it to throw
        $job = new ProcessDocumentJob($documentJob->id);

        try {
            $job->handle(app(\App\Services\Pipeline\PipelineOrchestrator::class));
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Refresh models
        $documentJob->refresh();

        // Job should have incremented attempts but not be marked as failed yet (can retry)
        expect($documentJob->attempts)->toBe(1)
            ->and($documentJob->canRetry())->toBeTrue();
    });

    test('job marks as failed after exhausting retries', function () {
        // Register a failing processor
        $failingProcessor = new class extends AbstractProcessor
        {
            protected string $name = 'FailingProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                throw new \Exception('Processor failed intentionally');
            }
        };

        app(ProcessorRegistry::class)->register('failing_processor_2', get_class($failingProcessor));

        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'attempts' => 2, // Already attempted twice
            'max_attempts' => 3,
            'pipeline_instance' => [
                'processors' => [
                    [
                        'id' => 'failing_processor_2',
                        'type' => 'test',
                        'config' => [],
                    ],
                ],
            ],
        ]);

        // Execute job (final attempt)
        $job = new ProcessDocumentJob($documentJob->id);

        try {
            $job->handle(app(\App\Services\Pipeline\PipelineOrchestrator::class));
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Refresh models
        $documentJob->refresh();
        $document->refresh();

        // Job should be marked as failed after exhausting retries
        expect($documentJob->attempts)->toBeGreaterThanOrEqual(3) // At least max_attempts
            ->and($documentJob->canRetry())->toBeFalse()
            ->and($documentJob->isFailed())->toBeTrue()
            ->and($document->isFailed())->toBeTrue();
    });

    test('job processes multiple processors in sequence', function () {
        // Register multiple test processors
        $processor1 = new class extends AbstractProcessor
        {
            protected string $name = 'Processor1';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['step' => 1, 'data' => 'first'];
            }
        };

        $processor2 = new class extends AbstractProcessor
        {
            protected string $name = 'Processor2';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['step' => 2, 'data' => 'second'];
            }
        };

        $registry = app(ProcessorRegistry::class);
        $registry->register('proc_1', get_class($processor1));
        $registry->register('proc_2', get_class($processor2));
        $this->app->instance(ProcessorRegistry::class, $registry);

        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    ['id' => 'proc_1', 'type' => 'test', 'config' => []],
                    ['id' => 'proc_2', 'type' => 'test', 'config' => []],
                ],
            ],
        ]);

        // Execute job
        $job = new ProcessDocumentJob($documentJob->id);
        $job->handle(app(\App\Services\Pipeline\PipelineOrchestrator::class));

        // Verify both processors executed
        $executions = ProcessorExecution::where('job_id', $documentJob->id)
            ->orderBy('created_at')
            ->get();

        expect($executions)->toHaveCount(2)
            ->and($executions[0]->output_data)->toBe(['step' => 1, 'data' => 'first'])
            ->and($executions[1]->output_data)->toBe(['step' => 2, 'data' => 'second'])
            ->and($executions[0]->isCompleted())->toBeTrue()
            ->and($executions[1]->isCompleted())->toBeTrue();
    });

    test('job is tenant-aware', function () {
        // Register test processor and bind to container
        $testProcessor = new class extends AbstractProcessor
        {
            protected string $name = 'TestProcessor';

            protected string $category = 'test';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['status' => 'processed', 'test' => true];
            }
        };

        $registry = app(ProcessorRegistry::class);
        $registry->register('test_processor', get_class($testProcessor));
        $this->app->instance(ProcessorRegistry::class, $registry);

        $campaign = Campaign::factory()->create();

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);

        $documentJob = DocumentJob::factory()->create([
            'document_id' => $document->id,
            'pipeline_instance' => [
                'processors' => [
                    [
                        'id' => 'test_processor',
                        'type' => 'test',
                        'config' => [],
                    ],
                ],
            ],
        ]);

        // Verify all models are on tenant connection
        expect($campaign->getConnectionName())->toBe('tenant')
            ->and($document->getConnectionName())->toBe('tenant')
            ->and($documentJob->getConnectionName())->toBe('tenant');

        // Execute job
        $job = new ProcessDocumentJob($documentJob->id);
        $job->handle(app(\App\Services\Pipeline\PipelineOrchestrator::class));

        // Verify processor execution is also on tenant connection
        $execution = ProcessorExecution::where('job_id', $documentJob->id)->first();
        expect($execution->getConnectionName())->toBe('tenant');
    });
});
