# Laravel Workflow + PortPHP Architecture

## Status: **Phase 5 Complete** - Migration Finished

This document explains the architecture of **Laravel Workflow** (a durable workflow engine) used for document processing pipelines.

---

## What is Laravel Workflow?

Laravel Workflow is inspired by **Temporal** and **Azure Durable Functions**. It's NOT a state machine framework - it's a **durable execution engine**.

### Key Concepts

**Workflow**: Orchestration code that coordinates Activities
- Written using PHP generators (`yield` keyword)
- Automatically checkpointed at each `yield`
- Can resume from last checkpoint if crashed
- Runs on queue workers

**Activity**: Unit of work (like our current Processors)
- Isolated execution (has its own retry/timeout config)
- Can be tested independently
- Automatically retried on failure
- Executed asynchronously

### Example: Simple Workflow

```php
class MyWorkflow extends Workflow
{
    public function execute($name)
    {
        // Each yield creates a checkpoint
        $result1 = yield ActivityStub::make(Activity1::class, $name);
        $result2 = yield ActivityStub::make(Activity2::class, $result1);
        
        return $result2;
    }
}

// Usage
$workflow = WorkflowStub::make(MyWorkflow::class);
$workflow->start('input');

while ($workflow->running());  // Poll until complete
$output = $workflow->output(); // Get final result
```

---

## Current Architecture vs. Laravel Workflow

### Current Architecture: Laravel Workflow

```
DocumentProcessingPipeline::process()
  ↓
Create DocumentJob
  ↓
Start DocumentProcessingWorkflow
  ↓
Laravel Workflow automatically:
  - Executes activities sequentially
  - Checkpoints state after each activity
  - Retries failed activities
  - Resumes from last checkpoint on crash
  - Handles tenant context per activity
  - Tracks workflow history
  - Fires events
  ↓
Activities (OcrActivity, ClassificationActivity, etc):
  - Initialize tenant context
  - Load DocumentJob
  - Execute existing processor (NO CHANGES)
  - Return results to workflow
```

**Benefits:**
- ✅ ~380 lines of orchestration code (56% reduction from previous 873 lines)
- ✅ Automatic state persistence
- ✅ Built-in retry/timeout per activity
- ✅ Workflow history/replay
- ✅ Easy to test (isolated activities)
- ✅ Visual workflow representation possible
- ✅ Declarative orchestration

**Previous Architecture Issues (Resolved)**:
- ❌ 873 lines of orchestration code
- ❌ Complex self-dispatch logic
- ❌ Manual state management (current_processor_index)
- ❌ Manual event firing
- ❌ Middleware boilerplate per job
- ❌ Hard to test (nested dependencies)
- ❌ No visual representation
- ❌ No workflow history/replay

---

## Proof of Concept Files

### 1. DocumentProcessingWorkflow.php

Location: `app/Workflows/DocumentProcessingWorkflow.php`

```php
class DocumentProcessingWorkflow extends Workflow
{
    public function execute(string $documentJobId, string $tenantId): array
    {
        // Activity 1: OCR
        $ocrResult = yield ActivityStub::make(
            OcrActivity::class,
            $documentJobId,
            $tenantId
        );

        // Activity 2: Classification (depends on OCR)
        $classificationResult = yield ActivityStub::make(
            ClassificationActivity::class,
            $documentJobId,
            $ocrResult,
            $tenantId
        );

        // Activity 3: Extraction
        $extractionResult = yield ActivityStub::make(
            ExtractionActivity::class,
            $documentJobId,
            $ocrResult,
            $classificationResult,
            $tenantId
        );

        // Activity 4: Validation
        $validationResult = yield ActivityStub::make(
            ValidationActivity::class,
            $documentJobId,
            $extractionResult,
            $tenantId
        );

        return [
            'ocr' => $ocrResult,
            'classification' => $classificationResult,
            'extraction' => $extractionResult,
            'validation' => $validationResult,
        ];
    }
}
```

**Key Points:**
- Each `yield` is a checkpoint
- If workflow crashes after OCR, it resumes from Classification
- Tenant context handled per-activity (not workflow-level middleware)
- Sequential execution with data dependencies clear
- No manual state management

### 2. OcrActivity.php

Location: `app/Workflows/Activities/OcrActivity.php`

```php
class OcrActivity extends Activity
{
    public function execute(string $documentJobId, string $tenantId): array
    {
        // 1. Initialize tenant context
        $tenant = Tenant::on('central')->findOrFail($tenantId);
        app(TenancyService::class)->initializeTenant($tenant);

        // 2. Load DocumentJob
        $documentJob = DocumentJob::findOrFail($documentJobId);
        $document = $documentJob->document;

        // 3. Get processor from registry (EXISTING CODE)
        $registry = app(ProcessorRegistry::class);
        $processor = $registry->get('ocr');

        // 4. Get config
        $config = ProcessorConfigData::from($processorConfig);
        $context = new ProcessorContextData(...);

        // 5. Execute processor (EXISTING CODE - NO CHANGES)
        $result = $processor->handle($document, $config, $context);

        if (!$result->success) {
            throw new \RuntimeException($result->error);
        }

        // 6. Store results
        $document->update(['metadata' => ['ocr_output' => $result->output]]);

        return $result->output;
    }
}
```

**Key Points:**
- Wraps existing `ProcessorInterface` - no changes to processors!
- Handles tenant context initialization
- Throws exception on failure (Laravel Workflow auto-retries)
- Returns data for next activity
- Fully isolated and testable

---

## Integration with Existing Code

### What Changes?

**✅ NO CHANGES:**
- `ProcessorInterface` - stays exactly the same
- `AbstractProcessor` - no changes
- Individual processors (OCR, Classification, etc.) - no changes
- `ProcessorRegistry` - no changes
- `Document`, `DocumentJob` models - no changes
- Frontend/API - no changes

**🔄 WRAPPED:**
- Each processor wrapped in an Activity class
- Activity handles tenant context + calls existing processor

**🗑️ REMOVED:**
- `ProcessDocumentJob` - replaced by Workflow execution
- `SetTenantContext` middleware - replaced by per-activity tenant init
- `DocumentProcessingPipeline::executeNextStage()` and related legacy methods - replaced by Workflow

**✨ NEW:**
- `DocumentProcessingWorkflow` - orchestrates activities
- `*Activity` classes - wrap existing processors
- Simplified `DocumentProcessingPipeline::process()` - just starts workflow

---

## Migration Strategy

### Phase 1: Foundation ✅ COMPLETE

- [x] Install `laravel-workflow/laravel-workflow`
- [x] Install `portphp/portphp` and `portphp/steps`
- [x] Create proof-of-concept `DocumentProcessingWorkflow`
- [x] Create sample `OcrActivity` wrapping existing processor
- [x] Document architecture

**Deliverable**: Packages installed, skeleton compiles, no runtime changes yet

### Phase 2: All Activities + E2E Test ✅ COMPLETE

- [x] Create `ClassificationActivity`, `ExtractionActivity`, `ValidationActivity`
- [x] Create test that runs workflow end-to-end (with mocked activities)
- [x] Verify tenant context pattern works per-activity
- [x] Publish and run Laravel Workflow migrations
- [x] All tests passing (5 tests, 29 assertions)
- [ ] Benchmark vs. current pipeline (deferred - needs real processor implementations)

**Deliverable**: Full activity set created, workflow tested with mocks, ready for integration

**Key Learnings**:
- Laravel Workflow's `WorkflowStub::fake()` enables synchronous testing
- Activities can be mocked with `WorkflowStub::mock()` for fast testing
- Workflow `execute()` method must not have array return type (it's a Generator)
- Database migrations required: `workflows`, `workflow_logs`, `workflow_signals`, etc.
- Activity pattern works perfectly - each activity initializes tenant context independently

### Phase 3: Feature Flag Integration ✅ COMPLETE

- [x] Add feature flag: `use_laravel_workflow`
- [x] Update `DocumentProcessingPipeline::process()` to branch:
  - If flag enabled: start workflow
  - If flag disabled: use existing pipeline
- [x] Event listeners for workflow completion/failure
- [x] Tests updated

### Phase 4: Advanced Features ✅ COMPLETE

- [x] Parallel execution (multiple activities at once) - demonstrated in `AdvancedDocumentProcessingWorkflow`
- [x] Conditional routing (document-type-specific flows) - demonstrated with if/match patterns
- [x] Retry configuration per activity
- [x] NonRetryableException handling

### Phase 5: Full Migration ✅ COMPLETE

- [x] Workflows enabled by default
- [x] Remove old pipeline code (ProcessDocumentJob, SetTenantContext middleware)
- [x] Update tests
- [x] Documentation updated

### Future Enhancements (Optional)

- [ ] Workflow versioning
- [ ] UI for workflow monitoring (using Waterline package)
- [ ] Performance benchmarking vs legacy system

---

## PortPHP Integration (Future)

PortPHP is an ETL framework with Reader → Processor → Writer pattern. We'll use it to:

1. **Standardize data transformations** within activities
2. **Add validation steps** (built-in schema validation)
3. **Support batch processing** (process multiple documents in parallel)

Example:

```php
class OcrActivity extends Activity
{
    public function execute(string $documentJobId, string $tenantId): array
    {
        // Initialize tenant...
        
        // Create PortPHP pipeline
        $workflow = new \PortPHP\Workflow(
            new DocumentReader($document)  // Reader
        );

        $workflow
            ->addStep(new ProcessorAdapter($processor, $config))  // Processor
            ->addStep(new ValidateOutputStep($schema))            // Validation
            ->setWriter(new DocumentMetadataWriter($document));   // Writer

        $result = $workflow->process();
        
        return $result;
    }
}
```

**Benefits:**
- Clear separation: Reader → Process → Validate → Write
- Built-in validation via PortPHP steps
- Reusable across activities
- Easy to test

---

## Testing Strategy

### Unit Tests: Activities

```php
public function test_ocr_activity_processes_document()
{
    // Setup
    $tenant = Tenant::factory()->create();
    $document = Document::factory()->create();
    $job = DocumentJob::factory()->create(['document_id' => $document->id]);

    // Execute activity directly (no workflow needed)
    $activity = new OcrActivity();
    $result = $activity->execute($job->id, $tenant->id);

    // Assert
    $this->assertArrayHasKey('text', $result);
    $this->assertNotNull($document->fresh()->metadata['ocr_output']);
}
```

### Integration Tests: Workflows

```php
public function test_workflow_processes_document_end_to_end()
{
    // Setup
    $tenant = Tenant::factory()->create();
    $document = Document::factory()->create();
    $job = DocumentJob::factory()->create(['document_id' => $document->id]);

    // Start workflow
    $workflow = WorkflowStub::make(
        DocumentProcessingWorkflow::class
    );
    $workflow->start($job->id, $tenant->id);

    // Wait for completion
    while ($workflow->running()) {
        sleep(1);
    }

    // Assert
    $result = $workflow->output();
    $this->assertArrayHasKey('ocr', $result);
    $this->assertArrayHasKey('classification', $result);
    $this->assertArrayHasKey('extraction', $result);
    $this->assertArrayHasKey('validation', $result);
}
```

---

## Benefits Summary

| Aspect | Before (Manual) | After (Laravel Workflow) | Improvement |
|--------|----------------|--------------------------|-------------|
| **Code Lines** | 873 lines | ~380 lines | 56% reduction |
| **State Management** | Manual (current_processor_index) | Automatic (checkpoints) | Simplified |
| **Retry Logic** | Job-level (retry entire job) | Activity-level (retry specific step) | Granular |
| **Tenant Context** | Middleware per job | Per-activity initialization | Clearer |
| **Event Firing** | Manual (14+ events) | Automatic | Simplified |
| **Testing** | Complex (nested mocks) | Isolated (test activities separately) | Easier |
| **Workflow History** | None | Built-in (replay, audit) | New capability |
| **Parallel Execution** | None | Native support | New capability |
| **Visual Representation** | None | Possible (via Waterline UI) | New capability |

---

## Next Steps

1. **Review this POC** - Does the Laravel Workflow approach make sense?
2. **Complete Phase 2** - Create remaining activities, run E2E test
3. **Benchmark** - Compare performance vs. current pipeline
4. **Go/No-Go Decision** - Continue migration or revert based on Phase 2 results

---

## Questions?

- **Q: Do we need to rewrite all processors?**  
  A: No! Activities wrap existing processors. `ProcessorInterface` stays unchanged.

- **Q: What about existing DocumentJobs in the queue?**  
  A: Feature flag ensures old jobs use old pipeline. Only new documents use workflow.

- **Q: What if Laravel Workflow doesn't meet our needs?**  
  A: Phase 2 is the decision point. If benchmarks fail, we keep current pipeline.

- **Q: How does tenant context work?**  
  A: Each activity initializes tenant context at start. No workflow-level middleware needed.

- **Q: Can we have parallel execution?**  
  A: Yes! Laravel Workflow supports parallel activities. Example:
  ```php
  // Run classification + extraction in parallel after OCR
  [$classResult, $extractResult] = yield Workflow::all([
      ActivityStub::make(ClassificationActivity::class, ...),
      ActivityStub::make(ExtractionActivity::class, ...)
  ]);
  ```

---

**Status**: ✅ Phase 1 Complete - Ready for Phase 2
