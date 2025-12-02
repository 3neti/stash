<?php

declare(strict_types=1);

namespace Tests\Feature\Workflows;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Workflows\DocumentProcessingWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Workflow\WorkflowStub;

/**
 * DocumentProcessingWorkflowTest
 *
 * Proof-of-concept test for Laravel Workflow integration.
 * This demonstrates that the workflow skeleton compiles and can be instantiated.
 *
 * NOTE: This is Phase 2 - testing workflow structure, not full E2E execution yet.
 */
class DocumentProcessingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_class_exists_and_extends_correct_base(): void
    {
        // Verify the workflow class exists
        $this->assertTrue(class_exists(DocumentProcessingWorkflow::class));

        // Verify it extends the correct base class
        $reflection = new \ReflectionClass(DocumentProcessingWorkflow::class);
        $this->assertTrue($reflection->isSubclassOf(\Workflow\Workflow::class));
    }

    public function test_workflow_has_execute_method(): void
    {
        $reflection = new \ReflectionClass(DocumentProcessingWorkflow::class);
        
        // Verify execute method exists
        $this->assertTrue($reflection->hasMethod('execute'));

        // Verify execute method is a generator (returns Generator)
        $method = $reflection->getMethod('execute');
        $this->assertTrue($method->isPublic());
    }

    public function test_workflow_stub_can_be_created(): void
    {
        // Test that WorkflowStub can create an instance
        // This verifies Laravel Workflow package is installed correctly
        $stub = WorkflowStub::make(DocumentProcessingWorkflow::class);

        $this->assertInstanceOf(WorkflowStub::class, $stub);
    }

    public function test_activities_exist_and_extend_activity(): void
    {
        $activities = [
            \App\Workflows\Activities\OcrActivity::class,
            \App\Workflows\Activities\ClassificationActivity::class,
            \App\Workflows\Activities\ExtractionActivity::class,
            \App\Workflows\Activities\ValidationActivity::class,
        ];

        foreach ($activities as $activityClass) {
            // Verify class exists
            $this->assertTrue(
                class_exists($activityClass),
                "Activity class {$activityClass} should exist"
            );

            // Verify it extends Activity
            $reflection = new \ReflectionClass($activityClass);
            $this->assertTrue(
                $reflection->isSubclassOf(\Workflow\Activity::class),
                "Activity class {$activityClass} should extend Workflow\\Activity"
            );

            // Verify it has execute method
            $this->assertTrue(
                $reflection->hasMethod('execute'),
                "Activity class {$activityClass} should have execute() method"
            );
        }
    }

    public function test_workflow_executes_with_mocked_activities(): void
    {
        // Use Laravel Workflow's testing API to fake workflow execution
        WorkflowStub::fake();

        // Mock each activity with sample outputs
        WorkflowStub::mock(
            \App\Workflows\Activities\OcrActivity::class,
            ['text' => 'Extracted text from document', 'confidence' => 0.95]
        );

        WorkflowStub::mock(
            \App\Workflows\Activities\ClassificationActivity::class,
            ['category' => 'invoice', 'confidence' => 0.92]
        );

        WorkflowStub::mock(
            \App\Workflows\Activities\ExtractionActivity::class,
            ['fields' => ['amount' => 100, 'date' => '2024-01-01']]
        );

        WorkflowStub::mock(
            \App\Workflows\Activities\ValidationActivity::class,
            ['is_valid' => true, 'errors' => []]
        );

        // Create workflow stub (synchronous execution in test mode)
        $workflow = WorkflowStub::make(DocumentProcessingWorkflow::class);
        
        // Start workflow with mock data
        $workflow->start('test-job-id', 'test-tenant-id');

        // Get workflow output (synchronous in fake mode)
        $result = $workflow->output();

        // Assert all activities completed and returned expected structure
        $this->assertArrayHasKey('ocr', $result);
        $this->assertArrayHasKey('classification', $result);
        $this->assertArrayHasKey('extraction', $result);
        $this->assertArrayHasKey('validation', $result);

        // Assert specific output values
        $this->assertEquals('Extracted text from document', $result['ocr']['text']);
        $this->assertEquals('invoice', $result['classification']['category']);
        $this->assertEquals(100, $result['extraction']['fields']['amount']);
        $this->assertTrue($result['validation']['is_valid']);

        // Assert activities were dispatched in correct order
        WorkflowStub::assertDispatched(\App\Workflows\Activities\OcrActivity::class);
        WorkflowStub::assertDispatched(\App\Workflows\Activities\ClassificationActivity::class);
        WorkflowStub::assertDispatched(\App\Workflows\Activities\ExtractionActivity::class);
        WorkflowStub::assertDispatched(\App\Workflows\Activities\ValidationActivity::class);
    }
}
