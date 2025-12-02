<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use App\Workflows\Activities\ClassificationActivity;
use App\Workflows\Activities\ExtractionActivity;
use App\Workflows\Activities\OcrActivity;
use App\Workflows\Activities\ValidationActivity;
use App\Workflows\AdvancedDocumentProcessingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Workflow\WorkflowStub;

class AdvancedFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations on central database (for workflow tables)
        $this->artisan('migrate', ['--database' => 'central', '--path' => 'database/migrations']);

        // Set up test data
        $this->setupTestTenantAndData();
    }

    private function setupTestTenantAndData(): void
    {
        // Create tenant on central DB
        $tenant = Tenant::on('central')->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);

        // Initialize tenant context
        app(TenancyService::class)->initializeTenant($tenant);

        // Create campaign on tenant DB
        $this->campaign = Campaign::create([
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'pipeline_config' => [
                'processors' => [
                    ['id' => 'ocr', 'config' => []],
                    ['id' => 'classification', 'config' => []],
                    ['id' => 'extraction', 'config' => []],
                    ['id' => 'validation', 'config' => []],
                ],
            ],
        ]);

        // Create document on tenant DB
        $this->document = Document::create([
            'campaign_id' => $this->campaign->id,
            'filename' => 'test.pdf',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'storage_path' => 'documents/test.pdf',
            'hash' => hash('sha256', 'test-document-content'),
        ]);
    }

    public function test_parallel_execution_with_activity_stub_all(): void
    {
        WorkflowStub::fake();

        $tenant = Tenant::on('central')->first();
        $job = DocumentJob::create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $this->document->id,
            'pipeline_instance' => $this->campaign->pipeline_config,
            'tenant_id' => $tenant->id,
        ]);

        // Mock activities with different execution times
        WorkflowStub::mock(OcrActivity::class, ['text' => 'Sample text', 'document_type' => 'invoice']);
        WorkflowStub::mock(ClassificationActivity::class, ['category' => 'invoice', 'confidence' => 0.95]);
        WorkflowStub::mock(ExtractionActivity::class, ['amount' => 100.00, 'date' => '2024-01-01']);
        WorkflowStub::mock(ValidationActivity::class, ['valid' => true]);

        // Execute workflow
        $workflow = WorkflowStub::make(AdvancedDocumentProcessingWorkflow::class);
        $workflow->start($job->id, $tenant->id);

        $result = $workflow->output();

        // Assert all activities executed
        $this->assertArrayHasKey('ocr', $result);
        $this->assertArrayHasKey('classification', $result);
        $this->assertArrayHasKey('extraction', $result);
        $this->assertArrayHasKey('validation', $result);

        // Assert parallel execution indicator
        $this->assertEquals('advanced', $result['execution_pattern']);

        // Verify activities were dispatched
        WorkflowStub::assertDispatched(OcrActivity::class);
        WorkflowStub::assertDispatched(ClassificationActivity::class);
        WorkflowStub::assertDispatched(ExtractionActivity::class);
        WorkflowStub::assertDispatched(ValidationActivity::class);
    }

    public function test_conditional_execution_based_on_document_type(): void
    {
        WorkflowStub::fake();

        $tenant = Tenant::on('central')->first();
        $job = DocumentJob::create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $this->document->id,
            'pipeline_instance' => $this->campaign->pipeline_config,
            'tenant_id' => $tenant->id,
        ]);

        // Mock OCR to return invoice type
        WorkflowStub::mock(OcrActivity::class, [
            'text' => 'Invoice text',
            'document_type' => 'invoice',
        ]);
        WorkflowStub::mock(ClassificationActivity::class, ['category' => 'invoice']);
        WorkflowStub::mock(ExtractionActivity::class, ['amount' => 100.00]);
        WorkflowStub::mock(ValidationActivity::class, ['valid' => true]);

        $workflow = WorkflowStub::make(AdvancedDocumentProcessingWorkflow::class);
        $workflow->start($job->id, $tenant->id);

        $result = $workflow->output();

        // Assert document type was detected and used
        $this->assertEquals('invoice', $result['document_type']);
        $this->assertEquals('invoice', $result['classification']['category']);
    }

    public function test_activities_have_retry_configuration(): void
    {
        // Verify activities have configured retry and timeout properties using reflection
        $ocrReflection = new \ReflectionClass(OcrActivity::class);
        $this->assertEquals(5, $ocrReflection->getDefaultProperties()['tries']);
        $this->assertEquals(300, $ocrReflection->getDefaultProperties()['timeout']);

        $classificationReflection = new \ReflectionClass(ClassificationActivity::class);
        $this->assertEquals(3, $classificationReflection->getDefaultProperties()['tries']);
        $this->assertEquals(120, $classificationReflection->getDefaultProperties()['timeout']);

        $extractionReflection = new \ReflectionClass(ExtractionActivity::class);
        $this->assertEquals(3, $extractionReflection->getDefaultProperties()['tries']);
        $this->assertEquals(180, $extractionReflection->getDefaultProperties()['timeout']);

        $validationReflection = new \ReflectionClass(ValidationActivity::class);
        $this->assertEquals(2, $validationReflection->getDefaultProperties()['tries']);
        $this->assertEquals(60, $validationReflection->getDefaultProperties()['timeout']);
    }

    public function test_workflow_handles_different_document_types(): void
    {
        WorkflowStub::fake();

        $tenant = Tenant::on('central')->first();

        // Test 1: Invoice
        $job1 = DocumentJob::create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $this->document->id,
            'pipeline_instance' => $this->campaign->pipeline_config,
            'tenant_id' => $tenant->id,
        ]);

        WorkflowStub::mock(OcrActivity::class, ['document_type' => 'invoice']);
        WorkflowStub::mock(ClassificationActivity::class, ['category' => 'invoice']);
        WorkflowStub::mock(ExtractionActivity::class, []);
        WorkflowStub::mock(ValidationActivity::class, []);

        $workflow1 = WorkflowStub::make(AdvancedDocumentProcessingWorkflow::class);
        $workflow1->start($job1->id, $tenant->id);
        $result1 = $workflow1->output();

        $this->assertEquals('invoice', $result1['document_type']);

        // Test 2: Receipt
        $job2 = DocumentJob::create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $this->document->id,
            'pipeline_instance' => $this->campaign->pipeline_config,
            'tenant_id' => $tenant->id,
        ]);

        WorkflowStub::mock(OcrActivity::class, ['document_type' => 'receipt']);
        WorkflowStub::mock(ClassificationActivity::class, ['category' => 'receipt']);
        WorkflowStub::mock(ExtractionActivity::class, []);
        WorkflowStub::mock(ValidationActivity::class, []);

        $workflow2 = WorkflowStub::make(AdvancedDocumentProcessingWorkflow::class);
        $workflow2->start($job2->id, $tenant->id);
        $result2 = $workflow2->output();

        $this->assertEquals('receipt', $result2['document_type']);

        // Test 3: Generic
        $job3 = DocumentJob::create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $this->document->id,
            'pipeline_instance' => $this->campaign->pipeline_config,
            'tenant_id' => $tenant->id,
        ]);

        WorkflowStub::mock(OcrActivity::class, ['document_type' => 'generic']);
        WorkflowStub::mock(ClassificationActivity::class, ['category' => 'other']);
        WorkflowStub::mock(ExtractionActivity::class, []);
        WorkflowStub::mock(ValidationActivity::class, []);

        $workflow3 = WorkflowStub::make(AdvancedDocumentProcessingWorkflow::class);
        $workflow3->start($job3->id, $tenant->id);
        $result3 = $workflow3->output();

        $this->assertEquals('generic', $result3['document_type']);
    }
}
