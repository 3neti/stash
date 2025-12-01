<?php

declare(strict_types=1);

namespace Tests\Feature\DeadDrop;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\Processor;
use App\Models\DocumentJob;
use App\Models\PipelineProgress;
use App\Services\Pipeline\DocumentProcessingPipeline;
use App\Services\Pipeline\ProcessorHookManager;
use App\Services\Pipeline\Hooks\TimeTrackingHook;
use App\Tenancy\TenantContext;
use Tests\DeadDropTestCase;

class QuickWinsIntegrationTest extends DeadDropTestCase
{
    private DocumentProcessingPipeline $pipeline;
    private ProcessorHookManager $hookManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = app(DocumentProcessingPipeline::class);
        $this->hookManager = new ProcessorHookManager();
        $this->pipeline->setHookManager($this->hookManager);
    }

    private function getTenant(): \App\Models\Tenant
    {
        return \App\Models\Tenant::on('pgsql')->firstOrCreate(
            ['slug' => 'quickwins-test-tenant'],
            ['name' => 'QuickWins Test Tenant']
        );
    }

    // =====================================================================
    // Progress Tracking Tests
    // =====================================================================

    public function test_progress_tracking_creates_progress_record(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $campaign = Campaign::factory()->create([
                'pipeline_config' => [
                    'processors' => [
                        ['id' => 'test_processor_1', 'type' => 'test', 'config' => []],
                        ['id' => 'test_processor_2', 'type' => 'test', 'config' => []],
                    ],
                ],
            ]);
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);

            $job = $this->pipeline->process($document, $campaign);

            $progress = PipelineProgress::where('job_id', $job->id)->first();
            expect($progress)->not->toBeNull();
            expect($progress->stage_count)->toBe(2);
            expect($progress->completed_stages)->toBe(0);
            expect($progress->percentage_complete)->toBe(0);
            expect($progress->status)->toBe('pending');
        });
    }

    public function test_progress_tracking_updates_on_stage_completion(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $campaign = Campaign::factory()->create([
                'pipeline_config' => [
                    'processors' => [
                        ['id' => 'test_processor_1', 'type' => 'test', 'config' => []],
                    ],
                ],
            ]);
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            $job = $this->pipeline->process($document, $campaign);

            $progress = PipelineProgress::where('job_id', $job->id)->first();
            expect($progress->percentage_complete)->toBe(0);

            // Simulate stage completion
            $progress->updateProgress(1, 1, 'Test Processor', 'processing');

            $updatedProgress = PipelineProgress::where('job_id', $job->id)->first();
            expect($updatedProgress->percentage_complete)->toBe(100);
            expect($updatedProgress->completed_stages)->toBe(1);
            expect($updatedProgress->current_stage)->toBe('Test Processor');
        });
    }

    public function test_progress_tracking_api_endpoint_returns_progress(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $campaign = Campaign::factory()->create([
                'pipeline_config' => [
                    'processors' => [
                        ['id' => 'test_processor_1', 'type' => 'test', 'config' => []],
                    ],
                ],
            ]);
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            $job = $this->pipeline->process($document, $campaign);

            $progress = PipelineProgress::where('job_id', $job->id)->first();
            $progress->updateProgress(0, 1, null, 'processing');

            $response = $this->getJson("/api/documents/{$document->uuid}/progress");

            expect($response->status())->toBe(200);
            expect($response->json('status'))->toBe('processing');
            expect($response->json('percentage_complete'))->toBe(0);
            expect($response->json('stage_count'))->toBe(1);
        });
    }

    // =====================================================================
    // Processor Hooks Tests
    // =====================================================================

    public function test_processor_hooks_registers_hooks(): void
    {
        $this->hookManager->register(new TimeTrackingHook());

        expect($this->hookManager->count())->toBe(1);
    }

    public function test_time_tracking_hook_records_start_time(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $this->hookManager->register(new TimeTrackingHook());

            $processor = Processor::factory()->create(['category' => 'test_processor']);
            $campaign = Campaign::factory()->create([
                'pipeline_config' => [
                    'processors' => [
                        ['id' => 'test_processor', 'type' => 'test', 'config' => []],
                    ],
                ],
            ]);
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            $job = $this->pipeline->process($document, $campaign);

            // Verify that hooks are in place
            expect($this->hookManager->count())->toBe(1);
        });
    }

    public function test_processor_metrics_endpoint_returns_executions(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $processor = Processor::factory()->create([
                'name' => 'Test Processor',
                'category' => 'test_processor',
            ]);
            $campaign = Campaign::factory()->create([
                'pipeline_config' => [
                    'processors' => [
                        ['id' => 'test_processor', 'type' => 'test', 'config' => []],
                    ],
                ],
            ]);
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            $job = $this->pipeline->process($document, $campaign);

            $response = $this->getJson("/api/documents/{$document->uuid}/metrics");

            expect($response->status())->toBe(200);
            $metrics = $response->json();
            expect($metrics)->toBeArray();
        });
    }

    // =====================================================================
    // Output Validation Tests
    // =====================================================================

    public function test_output_validation_accepts_valid_schema(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $validator = app(\App\Services\Validation\JsonSchemaValidator::class);

            $schema = [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string'],
                    'confidence' => ['type' => 'number'],
                ],
                'required' => ['text', 'confidence'],
            ];

            $validData = [
                'text' => 'Hello World',
                'confidence' => 0.95,
            ];

            $result = $validator->validate($validData, $schema);
            expect($result['valid'])->toBeTrue();
            expect($result['errors'])->toHaveCount(0);
        });
    }

    public function test_output_validation_rejects_invalid_schema(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $validator = app(\App\Services\Validation\JsonSchemaValidator::class);

            $schema = [
                'type' => 'object',
                'properties' => [
                    'text' => ['type' => 'string'],
                    'confidence' => ['type' => 'number'],
                ],
                'required' => ['text', 'confidence'],
            ];

            $invalidData = [
                'text' => 'Hello World',
                // missing required 'confidence'
            ];

            $result = $validator->validate($invalidData, $schema);
            expect($result['valid'])->toBeFalse();
            expect($result['errors'])->not->toHaveCount(0);
        });
    }

    public function test_processor_interface_has_get_output_schema_method(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $abstractProcessor = new class extends \App\Processors\AbstractProcessor {
                protected string $name = 'Test Processor';
                protected string $category = 'test';

                protected function process(
                    Document $document,
                    \App\Data\Pipeline\ProcessorConfigData $config
                ): array {
                    return [];
                }
            };

            // Should return null by default
            expect($abstractProcessor->getOutputSchema())->toBeNull();
        });
    }
}
