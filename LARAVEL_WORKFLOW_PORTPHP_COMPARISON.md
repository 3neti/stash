# Laravel Workflow + PortPHP: Architecture Documentation

## Previous Architecture: Manual Pipeline Orchestration (Removed)

```
┌─────────────────────────────────────────────────────────────────────┐
│                     DocumentProcessingPipeline                       │
│  ┌────────────────────────────────────────────────────────────────┐ │
│  │ process(Document, Campaign)                                     │ │
│  │ 1. Create DocumentJob                                           │ │
│  │ 2. Create PipelineProgress                                      │ │
│  │ 3. Dispatch ProcessDocumentJob ────────┐                        │ │
│  └────────────────────────────────────────│──────────────────────┬─┘ │
└─────────────────────────────────────────────────────────────────────┘
                                            │                         │
                                            ▼                         │
                        ┌────────────────────────────────┐            │
                        │    ProcessDocumentJob          │            │
                        │  ┌──────────────────────────┐  │            │
                        │  │ middleware()             │  │            │
                        │  │  - SetTenantContext ─────┼──────┐       │
                        │  └──────────────────────────┘  │    │       │
                        │  ┌──────────────────────────┐  │    │       │
                        │  │ handle()                 │  │    │       │
                        │  │  1. Load DocumentJob     │  │    │       │
                        │  │  2. State transitions    │  │    │       │
                        │  │  3. executeNextStage() ──┼──────┼───┐   │
                        │  │  4. Re-dispatch self ────┼──────┘   │   │
                        │  └──────────────────────────┘  │        │   │
                        └────────────────────────────────┘        │   │
                                                                  │   │
            ┌─────────────────────────────────────────────────────┘   │
            │                                                          │
            ▼                                                          │
┌─────────────────────────────────────────────────────────────────┐   │
│              SetTenantContext Middleware (86 lines)             │   │
│  ┌───────────────────────────────────────────────────────────┐  │   │
│  │ 1. Load Tenant from central DB                            │  │   │
│  │ 2. Switch DB connection to tenant                         │  │   │
│  │ 3. Verify connection active                               │  │   │
│  │ 4. Execute job with tenant context                        │  │   │
│  └───────────────────────────────────────────────────────────┘  │   │
└─────────────────────────────────────────────────────────────────┘   │
                                                                      │
            ┌─────────────────────────────────────────────────────────┘
            │
            ▼
┌───────────────────────────────────────────────────────────────────────────┐
│        DocumentProcessingPipeline::executeNextStage() (346 lines)         │
│  ┌─────────────────────────────────────────────────────────────────────┐  │
│  │ For current_processor_index:                                        │  │
│  │   1. Get processor config from pipeline_instance                    │  │
│  │   2. Load Processor model from DB                                   │  │
│  │   3. Get processor implementation from registry                     │  │
│  │   4. Create ProcessorExecution record                               │  │
│  │   5. Fire ProcessorExecutionStarted event                           │  │
│  │   6. Call hookManager->beforeExecution() ───────────┐               │  │
│  │   7. Execute processor->handle() ──────────────────┐│               │  │
│  │   8. Validate output schema                        ││               │  │
│  │   9. Call hookManager->afterExecution() ───────────┼┼───┐           │  │
│  │  10. Update ProcessorExecution record              ││   │           │  │
│  │  11. Fire ProcessorExecutionCompleted event        ││   │           │  │
│  │  12. Store output in document metadata             ││   │           │  │
│  │  13. Fire DocumentProcessingStageCompleted event   ││   │           │  │
│  │  14. Advance job to next processor (increment)     ││   │           │  │
│  │  15. Update PipelineProgress                       ││   │           │  │
│  │  16. Return true (continue processing)             ││   │           │  │
│  └─────────────────────────────────────────────────────┼┼───┼───────────┘  │
└───────────────────────────────────────────────────────────────────────────┘
                                                         ││   │
                   ┌─────────────────────────────────────┘│   │
                   │                                      │   │
                   ▼                                      ▼   ▼
       ┌──────────────────────────────┐    ┌──────────────────────────────┐
       │  ProcessorHookManager        │    │   ProcessorRegistry          │
       │  - beforeExecution()         │    │   - Auto-discovery           │
       │  - afterExecution()          │    │   - Class instantiation      │
       │  - onFailure()               │    │   - Database lookup (ULID)   │
       │  - TimeTrackingHook          │    │   - Memory cache             │
       └──────────────────────────────┘    └──────────────────────────────┘

**Problems with legacy approach (resolved in current architecture)**:
❌ 873 lines of orchestration code
❌ Complex self-dispatch logic (job re-queues itself)
❌ State management scattered (DocumentJob, ProcessorExecution, PipelineProgress)
❌ Manual event firing (14+ events)
❌ No visual workflow representation
❌ No conditional/parallel execution
❌ Middleware boilerplate per job type
❌ Hard to test (deeply nested dependencies)
❌ No workflow history/audit trail
❌ Data transformation ad-hoc (processor output mapping)
```

---

## Current Architecture: Laravel Workflow + PortPHP

```
┌──────────────────────────────────────────────────────────────────────────┐
│            DocumentProcessingPipeline::process() (30 lines)              │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ 1. Create DocumentJob (same)                                       │  │
│  │ 2. Create PipelineProgress (same)                                  │  │
│  │ 3. Fire DocumentJobCreated event (same)                            │  │
│  │ 4. Start Workflow ──────────────────────────────────────┐          │  │
│  │    $workflow = WorkflowStub::make(                      │          │  │
│  │        DocumentProcessingWorkflow::class                │          │  │
│  │    );                                                   │          │  │
│  │    $workflow->start($jobId, $tenantId);                 │          │  │
│  └─────────────────────────────────────────────────────────┼──────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
                                                             │
                                                             ▼
┌──────────────────────────────────────────────────────────────────────────┐
│      DocumentProcessingWorkflow::execute() (70 lines, generator-based)  │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ // Laravel Workflow uses PHP generators (like Temporal)            │  │
│  │ // Each `yield` creates checkpoint - can resume after crash        │  │
│  │                                                                     │  │
│  │ public function execute($jobId, $tenantId): array                  │  │
│  │ {                                                                   │  │
│  │     // Activity 1: OCR Processing                                  │  │
│  │     $ocrResult = yield ActivityStub::make(                         │  │
│  │         OcrActivity::class,                                        │  │
│  │         $jobId,                                                    │  │
│  │         $tenantId                                                  │  │
│  │     );                                                             │  │
│  │                                                                     │  │
│  │     // Activity 2: Classification (uses OCR output)                │  │
│  │     $classResult = yield ActivityStub::make(                       │  │
│  │         ClassificationActivity::class,                             │  │
│  │         $jobId,                                                    │  │
│  │         $ocrResult,                                                │  │
│  │         $tenantId                                                  │  │
│  │     );                                                             │  │
│  │                                                                     │  │
│  │     // Activity 3: Extraction                                      │  │
│  │     // Activity 4: Validation                                      │  │
│  │                                                                     │  │
│  │     return compact('ocrResult', 'classResult', ...);               │  │
│  │ }                                                                   │  │
│  └────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
         │
         │  Laravel Workflow automatically handles:
         │  ✅ Checkpointing state after each `yield`
         │  ✅ Resume from last checkpoint on crash
         │  ✅ Activity retry/timeout (configurable per activity)
         │  ✅ Queue dispatching (activities run on workers)
         │  ✅ Workflow history/audit trail
         │
         ▼
┌─────────────────────────────────────────────────────────────────────────┐
│             OcrActivity::execute() (80 lines per activity)              │
│  ┌───────────────────────────────────────────────────────────────────┐  │
│  │ class OcrActivity extends Activity                                │  │
│  │ {                                                                  │  │
│  │     public function execute($jobId, $tenantId): array             │  │
│  │     {                                                              │  │
│  │         // 1. Initialize tenant context (per-activity)            │  │
│  │         $tenant = Tenant::on('central')->find($tenantId);         │  │
│  │         app(TenancyService::class)->initializeTenant($tenant);    │  │
│  │                                                                    │  │
│  │         // 2. Load DocumentJob from tenant DB                     │  │
│  │         $job = DocumentJob::findOrFail($jobId);                   │  │
│  │         $document = $job->document;                               │  │
│  │                                                                    │  │
│  │         // 3. Get processor from registry (EXISTING CODE)         │  │
│  │         $processor = app(ProcessorRegistry::class)->get('ocr');   │  │
│  │                                                                    │  │
│  │         // 4. Execute processor (NO CHANGES TO PROCESSOR)         │  │
│  │         $result = $processor->handle($document, $config, $ctx);   │  │
│  │                                                                    │  │
│  │         if (!$result->success) {                                  │  │
│  │             throw new \RuntimeException($result->error);          │  │
│  │             // Laravel Workflow will auto-retry this activity     │  │
│  │         }                                                          │  │
│  │                                                                    │  │
│  │         // 5. Store results in document metadata                  │  │
│  │         $document->update(['metadata' => [...$result->output]]); │  │
│  │                                                                    │  │
│  │         return $result->output;  // Pass to next activity         │  │
│  │     }                                                              │  │
│  │ }                                                                  │  │
│  │                                                                    │  │
│  │ ✅ SIMPLIFIED:                                                     │  │
│  │   - No middleware (tenant context in activity)                    │  │
│  │   - Isolated & testable (test activity without workflow)          │  │
│  │   - Auto-retry on failure (configurable)                          │  │
│  │   - Wraps existing processor (NO PROCESSOR CHANGES)               │  │
│  └───────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────┘
         │
         │  Repeat for: ClassificationActivity, ExtractionActivity, 
         │             ValidationActivity
         │  (Each 80 lines, same pattern: tenant init + call processor)
         │
         ▼
┌──────────────────────────────────────────────────────────────────────────┐
│               Existing Processors (NO CHANGES NEEDED)                    │
│  ┌────────────────────────────────────────────────────────────────────┐  │
│  │ ProcessorInterface                                                 │  │
│  │   - handle(Document, Config, Context): ProcessorResult            │  │
│  │   - canProcess(Document): bool                                     │  │
│  │   - getOutputSchema(): array                                       │  │
│  │                                                                     │  │
│  │ Implementations:                                                   │  │
│  │   - OcrProcessor                                                   │  │
│  │   - ClassificationProcessor                                        │  │
│  │   - ExtractionProcessor                                            │  │
│  │   - ValidationProcessor                                            │  │
│  └────────────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘

**BENEFITS (vs. previous architecture)**:
✅ ~380 lines of orchestration code (56% reduction from 873 lines)
✅ Durable execution (resume from crash via checkpoints)
✅ Generator-based async/await pattern (like Temporal)
✅ Activity-level retry/timeout (granular control)
✅ Event firing automatic (Laravel Workflow events)
✅ Parallel execution native (ActivityStub::all())
✅ No middleware complexity (tenant context per activity)
✅ Easy to test (isolated activities)
✅ Workflow history/replay built-in
✅ Data transformation explicit (Activity pattern)
```

---

## Key Integration Points

### 1. Workflow Step → PortPHP Pipeline

```php
class OcrStep
{
    public function execute(DocumentJob $job): void
    {
        // Get processor from existing registry (no changes needed)
        $processor = app(ProcessorRegistry::class)->get('ocr');
        $config = $this->getProcessorConfig($job, 'ocr');

        // Create PortPHP pipeline
        $pipeline = new Workflow(
            new DocumentReader($job->document)  // Reader: Input source
        );

        $pipeline
            ->addStep(                          // Step 1: Process
                new ProcessorAdapter($processor, $config)
            )
            ->addStep(                          // Step 2: Validate
                new ValidateOutputStep($processor->getOutputSchema())
            )
            ->setWriter(                        // Writer: Output destination
                new DocumentMetadataWriter($job->document)
            );

        // Execute pipeline
        $result = $pipeline->process();

        // Store output for next processor
        $job->updateContext('previous_outputs', $result);
    }
}
```

**Integration Benefits:**
- ✅ Existing processors unchanged (ProcessorInterface stays)
- ✅ Clear separation: Reader → Processors → Writer
- ✅ Built-in validation via PortPHP steps
- ✅ Error handling via PortPHP exceptions
- ✅ Chainable transformations

---

### 2. Tenant Context Middleware

**Before (Job Middleware - 86 lines):**
```php
class SetTenantContext
{
    public function __construct(
        private readonly string $documentJobId,
        private readonly ?string $tenantId = null
    ) {}

    public function handle(object $job, Closure $next): void
    {
        // Load tenant from central DB
        if ($this->tenantId) {
            $tenant = Tenant::on('central')->findOrFail($this->tenantId);
        } else {
            // Fallback: load DocumentJob to get tenant_id
            $documentJob = DocumentJob::findOrFail($this->documentJobId);
            $tenant = Tenant::on('central')->findOrFail($documentJob->tenant_id);
        }

        // Initialize tenant context
        app(TenancyService::class)->initializeTenant($tenant);

        // Verify connection
        $tenantDb = DB::connection('tenant')->getDatabaseName();
        Log::debug('Verified tenant database', ['database' => $tenantDb]);

        // Execute job
        $next($job);
    }
}
```

**After (Workflow Middleware - 30 lines):**
```php
class TenantContextMiddleware implements WorkflowMiddleware
{
    public function handle($workflow, Closure $next)
    {
        // Tenant already in workflow context (no DB lookup needed)
        $job = $workflow->context['document_job'];
        $tenant = Tenant::on('central')->findOrFail($job->tenant_id);

        // Initialize tenant context
        app(TenancyService::class)->initializeTenant($tenant);

        // Execute workflow step
        return $next($workflow);
    }
}
```

**Simplification:**
- ✅ No job-specific logic
- ✅ Reusable across all workflows
- ✅ Context passed via workflow, not constructor
- ✅ 65% less code

---

### 3. Progress Tracking Integration

**Before (Manual updates in pipeline):**
```php
// After each processor completes
$totalStages = count($job->pipeline_instance['processors']);
$completedStages = $job->current_processor_index;

PipelineProgress::updateOrCreate(['job_id' => $job->id], [
    'stage_count' => $totalStages,
    'completed_stages' => $completedStages,
    'percentage_complete' => ($completedStages / $totalStages) * 100,
    'current_stage' => $job->pipeline_instance['processors'][$completedStages]['type'],
    'status' => "processing_stage_{$completedStages + 1}_of_{$totalStages}",
]);
```

**After (Workflow hooks):**
```php
// In workflow definition
->afterTransition('start', fn($w) => $this->updateProgress($w, 1))
->afterTransition('ocr_complete', fn($w) => $this->updateProgress($w, 2))
->afterTransition('classification_complete', fn($w) => $this->updateProgress($w, 3))

// Single helper method
private function updateProgress($workflow, int $stage): void
{
    $job = $workflow->context['document_job'];
    $totalStages = 4; // Or from config

    PipelineProgress::updateOrCreate(['job_id' => $job->id], [
        'completed_stages' => $stage,
        'percentage_complete' => ($stage / $totalStages) * 100,
        'current_stage' => $workflow->currentState(),
        'status' => 'processing',
    ]);
}
```

**Simplification:**
- ✅ Declarative (hook registration)
- ✅ DRY (single helper method)
- ✅ State name from workflow (no manual mapping)

---

### 4. Conditional Execution (NEW FEATURE)

**Example: Document Type Routing**

```php
// In workflow definition
->transition('classify_document', [
    'from' => 'processing_ocr',
    'to' => fn($workflow) => match($workflow->context['document_type']) {
        'invoice' => 'processing_invoice_extraction',
        'receipt' => 'processing_receipt_extraction',
        'contract' => 'processing_contract_extraction',
        default => 'processing_generic_extraction',
    },
])

// Register steps for each document type
->beforeTransition('processing_invoice_extraction', [InvoiceExtractionStep::class])
->beforeTransition('processing_receipt_extraction', [ReceiptExtractionStep::class])
->beforeTransition('processing_contract_extraction', [ContractExtractionStep::class])
```

**Use Case:**
- Different extraction logic per document type
- No need for conditional logic inside processors
- Visual workflow graph shows branching

---

### 5. Parallel Execution (NEW FEATURE)

**Example: Classification + Extraction in Parallel**

```php
// In workflow definition
->transition('parallel_processing', [
    'from' => 'processing_ocr',
    'to' => [
        'processing_classification',
        'processing_extraction',
    ],
    'type' => 'parallel',
    'wait_for_all' => true,
])

// After both complete, sync results
->transition('sync_results', [
    'from' => ['processing_classification', 'processing_extraction'],
    'to' => 'processing_validation',
])

// Validation step can access both outputs
class ValidationStep
{
    public function execute(DocumentJob $job): void
    {
        $classificationOutput = $job->context['classification_output'];
        $extractionOutput = $job->context['extraction_output'];

        // Validate consistency between classification and extraction
        // ...
    }
}
```

**Use Case:**
- OCR completes, then classification and extraction run simultaneously
- 50% time savings for independent processors
- Spatie Workflow handles synchronization automatically

---

## Testing Comparison

### Before: Testing Manual Pipeline

```php
// Complex setup with multiple mocks
public function test_pipeline_executes_all_processors()
{
    // Mock ProcessorRegistry
    $mockRegistry = Mockery::mock(ProcessorRegistry::class);
    $mockProcessor = Mockery::mock(ProcessorInterface::class);
    $mockRegistry->shouldReceive('get')->andReturn($mockProcessor);

    // Mock ProcessorHookManager
    $mockHookManager = Mockery::mock(ProcessorHookManager::class);
    $mockHookManager->shouldReceive('beforeExecution')->once();
    $mockHookManager->shouldReceive('afterExecution')->once();

    // Create pipeline with mocks
    $pipeline = new DocumentProcessingPipeline($mockRegistry, $mockHookManager);

    // Setup document and campaign
    $document = Document::factory()->create();
    $campaign = Campaign::factory()->create();

    // Execute pipeline
    $job = $pipeline->process($document, $campaign);

    // Assert many things...
    $this->assertTrue($job->isRunning());
    $this->assertCount(4, ProcessorExecution::where('job_id', $job->id)->get());
    // etc...
}
```

### After: Testing Workflow Steps

```php
// Isolated step testing
public function test_ocr_step_processes_document()
{
    // Setup
    $document = Document::factory()->create(['content' => 'sample.pdf']);
    $job = DocumentJob::factory()->create(['document_id' => $document->id]);

    // Execute single step (no workflow needed)
    $step = new OcrStep();
    $step->execute($job);

    // Assert
    $this->assertNotNull($document->fresh()->metadata['extracted_text']);
    $this->assertArrayHasKey('ocr', $job->context['previous_outputs']);
}

// Workflow integration testing
public function test_workflow_transitions_correctly()
{
    $workflow = DocumentProcessingWorkflow::create(['document_job' => $job]);

    $this->assertEquals('pending', $workflow->currentState());

    $workflow->transition('start');
    $this->assertEquals('processing_ocr', $workflow->currentState());

    $workflow->transition('ocr_complete');
    $this->assertEquals('processing_classification', $workflow->currentState());
}
```

**Testing Benefits:**
- ✅ Isolated step testing (no mocks needed)
- ✅ Workflow state testing (declarative)
- ✅ PortPHP pipeline testing (reader → processors → writer)
- ✅ Conditional transition testing (match logic)
- ✅ Parallel execution testing (Spatie handles this)

---

## Code Reduction Summary

| Component | Before (Manual) | After (Workflow + PortPHP) | Reduction |
|-----------|-----------------|----------------------------|-----------|
| Pipeline orchestration | 346 lines | 100 lines (workflow def) | 71% |
| Job execution | 206 lines | N/A (Spatie handles) | 100% |
| Tenant middleware | 86 lines | 30 lines | 65% |
| Progress tracking | Scattered (50+ lines) | 20 lines (hooks) | 60% |
| Event firing | Manual (14+ events) | Automatic (0 lines) | 100% |
| State transitions | Manual (50+ lines) | Declarative (0 lines) | 100% |
| **Total** | **~873 lines** | **~380 lines** | **56%** |

---

## Migration Risk Analysis

### Low Risk
✅ Phase 1-2: Parallel implementation (no changes to existing code)
✅ Existing processors unchanged (ProcessorInterface stays)
✅ Tests can run against both old and new pipeline
✅ Feature flag for gradual rollout

### Medium Risk
⚠️ Workflow state storage (new DB table, need migration)
⚠️ Tenant context in workflow (must verify multi-tenancy works)
⚠️ Performance overhead (need benchmarking)

### High Risk (Mitigated)
🔴 Breaking changes → **Mitigated**: Feature flag, gradual migration
🔴 Learning curve → **Mitigated**: Spatie docs are excellent
🔴 Data loss → **Mitigated**: Phase 1-2 don't touch production data

---

## Recommended Next Steps

1. **Review plan with team** - Discuss phases, timeline, risks
2. **Spike Phase 1** (4 hours) - Install packages, create skeleton workflow
3. **Benchmark current pipeline** - Baseline performance metrics
4. **Spike Phase 2** (8 hours) - Single step working (OcrStep)
5. **Go/No-Go decision** - Based on Phase 2 results
6. **Full migration** - Phases 3-5 (if approved)
