# Document Processing - Robustness & Extensibility Proposal

## Current State Analysis

### Strengths
✅ **Event-driven architecture** - Well-defined events at each stage  
✅ **State machine** - DocumentJob with clear state transitions  
✅ **Processor registry** - Dynamic processor discovery and registration  
✅ **Tenant-aware** - Multi-tenant support built-in  
✅ **Retry logic** - Configurable exponential backoff  
✅ **Snapshot architecture** - Pipeline config captured at job creation  

### Current Limitations

❌ **No versioning** - Pipeline config changes affect in-flight jobs  
❌ **Limited error recovery** - Retry entire job from current stage, not processor-level  
❌ **Sequential execution only** - No parallel processor support  
❌ **Hard to pause/resume** - No midpoint save/restore capability  
❌ **No progress tracking** - Can't query real-time progress  
❌ **No rollback support** - No way to undo failed stages  
❌ **Limited observability** - No metrics/tracing beyond basic logging  
❌ **No resource constraints** - Can't limit memory/CPU per processor  
❌ **No processor chaining rules** - No validation of processor dependencies  
❌ **Processor output limits** - No size validation on processor outputs  

---

## Proposal 1: Processor-Level Checkpointing & Recovery

### Problem
Currently, if a processor fails or times out, the entire stage is retried from scratch. For long-running processors (OCR on 100MB PDFs), this is wasteful.

### Solution: Checkpoint System

```
Pipeline Execution:
  Stage 1 (OCR)       → CHECKPOINT A → Stage 2 (Classification) → CHECKPOINT B → etc.
  If Stage 2 fails    → Restart from CHECKPOINT A (skip OCR)
```

#### Implementation Steps

1. **Add ProcessorCheckpoint Model**
```php
class ProcessorCheckpoint extends Model {
    // job_id, processor_index, checkpoint_key, data, metadata
    // Stores intermediate results that can be reused on retry
}
```

2. **Update ProcessorInterface**
```php
interface ProcessorInterface {
    public function handle(Document $doc, array $config, array $context): ProcessorResult;
    public function supportsCheckpointing(): bool;  // New
    public function getCheckpointData(): array;      // New
    public function restoreFromCheckpoint(array $data): void;  // New
}
```

3. **Update DocumentProcessingPipeline**
```php
public function executeNextStage(DocumentJob $job): bool {
    // Check if checkpoint exists
    $checkpoint = ProcessorCheckpoint::where('job_id', $job->id)
        ->where('processor_index', $job->current_processor_index)
        ->first();
    
    if ($checkpoint && $processor->supportsCheckpointing()) {
        $processor->restoreFromCheckpoint($checkpoint->data);
        // Use cached output instead of re-processing
        return $this->handleStageResult($job, $execution, $checkpoint->cached_result);
    }
    
    // Normal execution...
}
```

#### Benefits
✅ Massive time savings for retry scenarios  
✅ Reduces resource waste  
✅ Optional per-processor  
✅ Backward compatible  

#### Complexity: **Medium** (8-12 hours)

---

## Proposal 2: Pipeline Versioning & Rollback

### Problem
If pipeline config changes, in-flight jobs use the old config (snapshot). But if you need to update config, there's no way to handle version mismatches.

### Solution: Versioned Pipeline Configurations

```
campaigns table:
  pipeline_config (current)
  pipeline_version: 1

pipeline_versions table:
  version: 1, 2, 3, ...
  config: {...}
  created_at: ...
  status: 'active' | 'deprecated'

document_jobs:
  pipeline_version: 1 (snapshot version)
```

#### Implementation

1. **Add PipelineVersion Model**
```php
class PipelineVersion extends Model {
    public function campaign();
    public function jobs();  // DocumentJobs using this version
}
```

2. **Add migration** - Add `pipeline_version` to campaigns & document_jobs

3. **Update Campaign Model**
```php
public function createPipelineVersion(array $config): PipelineVersion {
    $version = $this->pipelineVersions()->create([
        'version' => $this->pipelineVersions()->max('version') + 1,
        'config' => $config,
        'status' => 'active',
    ]);
    
    return $version;
}

public function rollbackToVersion(int $version): void {
    $oldVersion = $this->pipelineVersions()->findOrFail($version);
    $this->createPipelineVersion($oldVersion->config);
}
```

4. **Update DocumentProcessingPipeline**
```php
public function process(Document $document, Campaign $campaign): DocumentJob {
    $pipelineVersion = $campaign->currentPipelineVersion();
    
    $job = DocumentJob::create([
        'pipeline_instance' => $pipelineVersion->config,
        'pipeline_version_id' => $pipelineVersion->id,
        // ...
    ]);
}
```

#### Benefits
✅ Track config history  
✅ Rollback capability  
✅ Impact analysis ("X jobs using v1")  
✅ A/B testing support  

#### Complexity: **Medium** (6-10 hours)

---

## Proposal 3: Parallel Processor Support

### Problem
Some processors can run in parallel (OCR, then separately run Classification AND Extraction). Current sequential approach wastes time.

### Solution: DAG-Based Processor Scheduling

```
Current (Sequential):
  OCR → Classification → Extraction → Validation → COMPLETE

Possible (Parallel DAG):
  OCR →
    ├→ Classification
    ├→ Extraction
    └→ Custom Processing
       ↓
       Validation →
       COMPLETE
```

#### Implementation

1. **Update Pipeline Config Format**
```php
// Before (sequential)
'processors' => [
    ['id' => 'ocr', 'config' => {...}],
    ['id' => 'classification', 'config' => {...}],
]

// After (DAG with parallel support)
'processors' => [
    [
        'id' => 'ocr',
        'stage' => 1,
        'config' => {...},
        'dependencies' => [],
        'parallel_after' => false,
    ],
    [
        'id' => 'classification',
        'stage' => 2,
        'config' => {...},
        'dependencies' => ['ocr'],
        'parallel_with' => ['extraction'],  // Run alongside extraction
    ],
    [
        'id' => 'extraction',
        'stage' => 2,
        'config' => {...},
        'dependencies' => ['ocr'],
        'parallel_with' => ['classification'],
    ],
    [
        'id' => 'validation',
        'stage' => 3,
        'config' => {...},
        'dependencies' => ['classification', 'extraction'],
    ],
]
```

2. **New Model: ProcessorDAG**
```php
class ProcessorDAG extends Model {
    // job_id, stage_number, processors_in_stage[]
    // Precomputed DAG for the job
}
```

3. **Update DocumentProcessingPipeline**
```php
public function process(Document $document, Campaign $campaign): DocumentJob {
    $job = DocumentJob::create([...]);
    
    // Build DAG from pipeline config
    $dag = $this->buildDAG($campaign->pipeline_config);
    ProcessorDAG::create(['job_id' => $job->id, 'stages' => $dag]);
    
    // Dispatch STAGE 1 jobs (can run multiple ProcessDocumentJobs in parallel)
    foreach ($dag[1] as $processor) {
        ProcessDocumentStageJob::dispatch($job, $processor);
    }
}

private function buildDAG(array $config): array {
    // Topological sort to build stage numbers
    // Return: [stage_number => [processors]]
}
```

4. **New Job: ProcessDocumentStageJob** (parallel-aware)
```php
class ProcessDocumentStageJob implements ShouldQueue {
    public function __construct(
        public DocumentJob $job,
        public string $processorId,
        public int $stageNumber,
    ) {}
    
    public function handle() {
        // Execute single processor
        $pipeline->executeSingleProcessor($this->job, $this->processorId);
        
        // Check if all processors in this stage complete
        if ($this->allProcessorsInStageComplete($this->job, $this->stageNumber)) {
            // Dispatch next stage
            $this->dispatchNextStage();
        }
    }
}
```

#### Benefits
✅ 50%+ time savings on multi-stage pipelines  
✅ Better resource utilization  
✅ Flexible processor ordering  
✅ Supports complex workflows  

#### Complexity: **High** (20-30 hours)

---

## Proposal 4: Progress Tracking & Query API

### Problem
No way to query real-time progress. API clients can't show progress bars.

### Solution: Progress Tracking Model

```php
class PipelineProgress extends Model {
    // job_id, percentage_complete, stage_count, completed_stages
    // status: queued, processing_stage_1_of_4, paused, failed
    // eta_completion: ...
    // Updated after each stage completes
}
```

#### Implementation

1. **After each stage completes**
```php
private function handleStageResult(...) {
    // ... existing logic ...
    
    // Update progress
    $totalStages = count($job->pipeline_instance['processors']);
    $completedStages = $job->current_processor_index;
    
    PipelineProgress::updateOrCreate(['job_id' => $job->id], [
        'percentage_complete' => ($completedStages / $totalStages) * 100,
        'stage_count' => $totalStages,
        'completed_stages' => $completedStages,
        'current_stage' => $completedStages < $totalStages 
            ? $job->pipeline_instance['processors'][$completedStages]['id']
            : null,
        'last_updated_at' => now(),
    ]);
}
```

2. **New API Endpoint**
```php
Route::get('/api/documents/{uuid}/progress', function ($uuid) {
    $document = Document::where('uuid', $uuid)->firstOrFail();
    $progress = PipelineProgress::where('job_id', $document->documentJob->id)->first();
    
    return response()->json([
        'status' => $progress->status,
        'percentage_complete' => $progress->percentage_complete,
        'stage_count' => $progress->stage_count,
        'completed_stages' => $progress->completed_stages,
        'current_stage' => $progress->current_stage,
        'eta_seconds' => $progress->calculateETA(),
    ]);
});
```

3. **WebSocket Support** (Optional)
```php
// Broadcast progress updates in real-time
event(new DocumentProgressUpdated($job->id, $progress));
```

#### Benefits
✅ Real-time progress visibility  
✅ Better UX (progress bars)  
✅ Accurate ETA calculations  
✅ Client-side monitoring  

#### Complexity: **Low** (4-6 hours)

---

## Proposal 5: Resource Constraints & Rate Limiting

### Problem
Processor heavy operations (OCR, LLM calls) can overwhelm system or consume all quota.

### Solution: Processor Resource Limits

```php
// config/processors.php
return [
    'ocr' => [
        'max_concurrent' => 2,              // Max 2 concurrent OCR jobs
        'timeout_seconds' => 300,           // Timeout after 5 mins
        'max_file_size_mb' => 50,           // Don't process files > 50MB
        'rate_limit' => '10/minute',        // Max 10 OCR/minute
    ],
    'llm_classification' => [
        'max_concurrent' => 5,
        'timeout_seconds' => 60,
        'rate_limit_tokens' => 100000,      // Max 100k tokens/minute
    ],
];
```

#### Implementation

1. **Update ProcessorInterface**
```php
interface ProcessorInterface {
    public function getResourceRequirements(): ResourceRequirements;
    // Returns: memory_mb, timeout_seconds, etc.
}
```

2. **New Middleware: ResourceValidator**
```php
class ValidateProcessorResources {
    public function handle(ProcessDocumentJob $job, Closure $next) {
        $processor = $this->registry->get($processorId);
        $requirements = $processor->getResourceRequirements();
        
        // Check if we can execute now
        if ($this->isConcurrencyLimited($processorId, $requirements)) {
            // Re-queue with exponential backoff
            $job->release(now()->addSeconds(30));
            return;
        }
        
        // Execute with timeout
        return $this->withTimeout($requirements->timeout_seconds, fn() => $next($job));
    }
}
```

3. **Rate Limiter**
```php
public function executeNextStage(DocumentJob $job): bool {
    $processor = $this->registry->get($processorId);
    
    // Check rate limit
    if ($this->rateLimiter->tooManyAttempts($processorId, $processor->getRateLimit())) {
        // Queue for retry
        ProcessDocumentJob::dispatch($job)->delay(now()->addMinutes(1));
        return false;
    }
    
    // Execute...
}
```

#### Benefits
✅ Prevents system overload  
✅ Controlled quota usage  
✅ Better resource allocation  
✅ Graceful degradation  

#### Complexity: **Medium** (10-15 hours)

---

## Proposal 6: Dead Letter Queue & Manual Intervention

### Problem
Failed jobs are marked as failed but no way to manually retry or investigate interactively.

### Solution: Dead Letter Queue with Retry UI

```
Failed Job Flow:
  ProcessDocumentJob fails 3x → Moved to DLQ
  → Admin sees job in UI
  → Can:
     ① View full error trace + intermediate outputs
     ② Manually edit processor config
     ③ Skip failed processor
     ④ Retry from specific stage
     ⑤ Discard & resume at next stage
```

#### Implementation

1. **New Model: DeadLetterQueueJob**
```php
class DLQJob extends Model {
    public function documentJob();
    public function failedProcessor();
    
    public function skipProcessor(): void {
        $this->documentJob->advanceProcessor();
        ProcessDocumentJob::dispatch($this->documentJob);
    }
    
    public function retryFromStage(int $stage): void {
        $this->documentJob->current_processor_index = $stage;
        $this->documentJob->save();
        ProcessDocumentJob::dispatch($this->documentJob);
    }
}
```

2. **Admin Dashboard Page**
```vue
<!-- DLQ Management UI -->
<template>
  <div>
    <h1>Failed Jobs ({{ count }})</h1>
    
    <table>
      <tr v-for="job in jobs">
        <td>{{ job.document.name }}</td>
        <td>{{ job.failed_processor }}</td>
        <td>{{ job.error_message }}</td>
        <td>
          <button @click="retryJob(job)">Retry</button>
          <button @click="skipProcessor(job)">Skip</button>
          <button @click="editConfig(job)">Edit Config</button>
          <button @click="discard(job)">Discard</button>
        </td>
      </tr>
    </table>
  </div>
</template>
```

3. **Update Failed Job Handler**
```php
public function failed(Throwable $exception): void {
    // Move to DLQ instead of just marking failed
    DLQJob::create([
        'document_job_id' => $this->documentJobId,
        'failed_processor' => $this->currentProcessorId,
        'error_message' => $exception->getMessage(),
        'stack_trace' => $exception->getTraceAsString(),
    ]);
}
```

#### Benefits
✅ Debuggable failures  
✅ Manual recovery options  
✅ Observability for operations  
✅ Audit trail of manual interventions  

#### Complexity: **Medium** (12-18 hours)

---

## Proposal 7: Processor Hooks & Middleware

### Problem
No way to inject custom logic around processor execution (logging, metrics, auth checks).

### Solution: Hook System

```php
interface ProcessorHook {
    public function beforeExecution(Processor $processor, Document $doc, array $config): void;
    public function afterExecution(Processor $processor, ProcessorResult $result): void;
    public function onFailure(Processor $processor, Throwable $e): void;
}
```

#### Implementation

```php
class ProcessorHookManager {
    protected array $hooks = [];
    
    public function register(ProcessorHook $hook): void {
        $this->hooks[] = $hook;
    }
    
    public function beforeExecution(...$args): void {
        foreach ($this->hooks as $hook) {
            $hook->beforeExecution(...$args);
        }
    }
}

// Usage in pipeline
public function executeNextStage(DocumentJob $job): bool {
    // ... setup ...
    
    $this->hookManager->beforeExecution($processor, $job->document, $config);
    
    try {
        $result = $processor->handle(...);
        $this->hookManager->afterExecution($processor, $result);
    } catch (Throwable $e) {
        $this->hookManager->onFailure($processor, $e);
        throw;
    }
}
```

#### Built-in Hooks

1. **MetricsHook** - Track execution time, memory, tokens
2. **LoggingHook** - Structured logging
3. **CostTrackingHook** - Calculate API costs
4. **AlertingHook** - Send alerts on failures
5. **ValidationHook** - Validate output contracts

#### Benefits
✅ Extensible without modifying core  
✅ Separates concerns  
✅ Reusable across projects  
✅ Easy testing  

#### Complexity: **Low** (6-8 hours)

---

## Proposal 8: Output Schema Validation

### Problem
Processor outputs aren't validated. Bad data can break downstream processors.

### Solution: JSON Schema Validation

```php
interface ProcessorInterface {
    public function getOutputSchema(): array;  // JSON Schema
}

class OcrProcessor implements ProcessorInterface {
    public function getOutputSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'pages' => ['type' => 'array'],
            ],
            'required' => ['text', 'confidence'],
        ];
    }
}
```

#### Implementation

```php
private function handleStageResult(...): bool {
    if ($result->isSuccess()) {
        // Validate output against schema
        $validator = new JsonSchemaValidator();
        
        try {
            $validator->validate(
                $result->output,
                $processor->getOutputSchema()
            );
        } catch (ValidationException $e) {
            // Mark as invalid output
            $result = ProcessorResult::failed('Invalid processor output: '.$e->getMessage());
            return $this->handleStageResult($job, $execution, $result, $processorConfig);
        }
        
        // Continue with valid output...
    }
}
```

#### Benefits
✅ Early error detection  
✅ Contract enforcement  
✅ Better error messages  
✅ Documentation via schema  

#### Complexity: **Low** (4-6 hours)

---

## Proposal 9: Pipeline Dry Run & Validation

### Problem
Can't test pipeline without actually running it on documents. Pipeline config errors only discovered at runtime.

### Solution: Dry Run Mode

```php
class DryRunProcessor implements ProcessorInterface {
    // Returns mock output matching expected schema
    // Uses ~10ms instead of real processing time
}

public function dryRun(Campaign $campaign, Document $sampleDocument): array {
    $results = [];
    
    foreach ($campaign->pipeline_config['processors'] as $processorConfig) {
        $dryRunProcessor = DryRunProcessor::create($processorConfig);
        
        $result = $dryRunProcessor->handle($sampleDocument, $processorConfig['config'], []);
        
        $results[] = [
            'processor' => $processorConfig['id'],
            'success' => true,
            'output_sample' => $result->output,
            'duration_ms' => $result->metadata['duration_ms'],
        ];
    }
    
    return $results;
}
```

#### Usage

```php
// API Endpoint
Route::post('/api/campaigns/{id}/dry-run', function (Campaign $campaign) {
    $sampleDoc = $campaign->documents()->first() ?? Document::factory()->create();
    
    return response()->json(
        $campaign->dryRun($sampleDoc)
    );
});
```

#### Benefits
✅ Test before deploying  
✅ Validate processor chains  
✅ Quick feedback loop  
✅ No side effects  

#### Complexity: **Medium** (8-12 hours)

---

## Proposal 10: Conditional Processor Routing

### Problem
All documents follow the same pipeline. Can't have different flows for different document types.

### Solution: Conditional Routing

```php
// Pipeline config with conditionals
'processors' => [
    ['id' => 'ocr', 'config' => {...}],
    [
        'id' => 'conditional_router',
        'conditions' => [
            'if' => "metadata.document_type == 'invoice'",
            'then' => ['id' => 'invoice_extraction', ...],
            'else' => [
                'if' => "metadata.document_type == 'receipt'",
                'then' => ['id' => 'receipt_extraction', ...],
                'else' => ['id' => 'generic_extraction', ...],
            ],
        ],
    ],
    ['id' => 'validation', 'config' => {...}],
]
```

#### Implementation

```php
class ConditionalRouter implements ProcessorInterface {
    public function handle(Document $doc, array $config, array $context): ProcessorResult {
        $condition = $config['conditions'];
        
        while (isset($condition['if'])) {
            if ($this->evaluate($condition['if'], $doc)) {
                // Execute 'then' branch
                return $this->executeBranch($condition['then'], $doc, $context);
            } else if (isset($condition['else'])) {
                $condition = $condition['else'];
            } else {
                break;
            }
        }
        
        return ProcessorResult::successful([]);
    }
}
```

#### Benefits
✅ Dynamic routing  
✅ Document-type-specific flows  
✅ More flexible pipelines  
✅ Reduced config duplication  

#### Complexity: **Medium** (10-15 hours)

---

## Implementation Roadmap

### Phase 1: High-Impact, Low-Effort (Start Here)
| Proposal | Effort | Impact | Priority |
|----------|--------|--------|----------|
| Progress Tracking (4) | 4-6h | High | 🔴 P1 |
| Processor Hooks (7) | 6-8h | High | 🔴 P1 |
| Output Validation (8) | 4-6h | Medium | 🟡 P2 |
| Dry Run (9) | 8-12h | Medium | 🟡 P2 |

### Phase 2: Medium-Impact, Medium-Effort
| Proposal | Effort | Impact | Priority |
|----------|--------|--------|----------|
| Checkpointing (1) | 8-12h | Very High | 🔴 P1 |
| Pipeline Versioning (2) | 6-10h | High | 🟡 P2 |
| Resource Constraints (5) | 10-15h | High | 🟡 P2 |
| Dead Letter Queue (6) | 12-18h | Medium | 🟡 P2 |

### Phase 3: High-Impact, High-Effort (Later)
| Proposal | Effort | Impact | Priority |
|----------|--------|--------|----------|
| Parallel Execution (3) | 20-30h | Very High | 🟢 P3 |
| Conditional Routing (10) | 10-15h | Medium | 🟢 P3 |

---

## Recommended Quick Wins (This Week)

1. **Progress Tracking** (4-6 hours)
   - Most valuable for UX
   - Low complexity
   - No breaking changes
   - Can add WebSocket later

2. **Processor Hooks** (6-8 hours)
   - Enables logging/metrics
   - Foundation for other features
   - Extensible architecture

3. **Output Schema Validation** (4-6 hours)
   - Prevents downstream errors
   - Easy to implement
   - Catches bugs early

**Total: 14-20 hours for massive robustness gains**

---

## Questions for You

1. **Parallel Execution**: How important? Do you have processors that can run in parallel?
2. **Versioning**: How often do pipeline configs change? Do you need rollback?
3. **Checkpointing**: Any processors that take > 5 minutes?
4. **Observability**: What metrics matter most (time, cost, success rate)?
5. **Manual Intervention**: How important is operational flexibility vs automation?

Let me know which proposals appeal most, and I'll create a detailed implementation plan!
