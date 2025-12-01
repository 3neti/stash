# Document Processing Pipeline - Complete Explanation

## Overview

The document processing pipeline is the **heart** of Stash. It orchestrates how documents flow through a series of processors (OCR, Classification, Extraction, Validation, etc.) in a configurable sequence.

Think of it like an **assembly line in a factory**:
- Documents enter as raw materials
- Each processor is a workstation that performs one task
- Documents move through stations one by one
- Final output is processed documents with extracted data

---

## Architecture Layers

```
┌─────────────────────────────────────────────────────────────┐
│                    USER INTERACTION                         │
│              (Upload document via web/API)                  │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            REQUEST MIDDLEWARE LAYER                         │
│    (InitializeTenantFromUser - set up tenant context)      │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            INGESTION LAYER                                  │
│  (UploadDocument Action - validate, store file, create DB) │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            PIPELINE DISPATCH LAYER                          │
│     (DocumentProcessingPipeline::process - create job)      │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            QUEUE LAYER                                      │
│       (ProcessDocumentJob dispatched to queue)              │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            JOB EXECUTION LAYER (Queue Worker)               │
│    (ProcessDocumentJob::handle - execute each processor)    │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            PROCESSOR EXECUTION LAYER                        │
│  (Pipeline::executeNextStage - run one processor at a time) │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            RESULT HANDLING LAYER                            │
│     (Pipeline::handleStageResult - success/failure/retry)   │
└──────────────────────┬──────────────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────────────┐
│            COMPLETION LAYER                                 │
│         (Mark document complete or failed)                  │
└─────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Flow

### 1️⃣ USER UPLOADS DOCUMENT

**Location**: Web UI or API endpoint

**What happens**:
- User selects PDF/image from their computer
- Sends to `/api/campaigns/{campaign}/documents`
- Arrives with file in `documents[]` array

**Code**:
```php
// Route
Route::post('documents', UploadDocument::class)->middleware('InitializeTenantFromUser');

// Request arrives here
POST /api/campaigns/01abc.../documents
[
  'documents[]' => [File]
]
```

---

### 2️⃣ REQUEST MIDDLEWARE INITIALIZES TENANT

**Location**: `app/Http/Middleware/InitializeTenantFromUser.php`

**What happens**:
- Laravel receives HTTP request
- Middleware runs BEFORE controller
- Gets authenticated user
- Looks up user's tenant
- **Initializes tenant context** (switches database connection)
- Now all queries use tenant database

**Code**:
```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();  // Authenticated user
    
    if ($user && $user->tenant_id) {
        $tenant = Tenant::on('pgsql')->find($user->tenant_id);
        
        // THIS IS CRITICAL: Initialize tenant context
        $tenancyService = app(TenancyService::class);
        $tenancyService->initializeTenant($tenant);  // ← Sets tenant connection
    }
    
    $response = $next($request);  // Controller now runs with tenant context
    
    // Keep context active for event listeners during response generation
    return $response;
}
```

**Key Point**: Without this, all queries fail with "Undefined table" because they try to query `campaigns` table in central database instead of tenant database.

---

### 3️⃣ FILE VALIDATION & STORAGE

**Location**: `app/Actions/Documents/UploadDocument.php`

**What happens**:
- Validate file (type, size, MIME)
- Generate unique document ID
- Calculate file hash
- Store file to tenant-scoped storage path
- Create Document record in database

**Code**:
```php
public function handle(Campaign $campaign, UploadedFile $file): Document
{
    // 1. Generate IDs
    $documentId = (string) new Ulid;  // Unique ID
    $hash = hash_file('sha256', $file->getRealPath());
    
    // 2. Generate storage path (tenant-scoped)
    $storagePath = $this->getTenantStoragePath($campaign, $documentId, $file);
    // Result: tenants/01abc.../documents/2025/12/01abc...pdf
    
    // 3. Store file
    $file->storeAs(dirname($storagePath), basename($storagePath), 'tenant');
    
    // 4. Create database record
    $document = Document::create([
        'id' => $documentId,
        'uuid' => (string) Str::uuid(),
        'campaign_id' => $campaign->id,
        'original_filename' => $file->getClientOriginalName(),
        'mime_type' => $file->getMimeType(),
        'size_bytes' => $file->getSize(),
        'storage_path' => $storagePath,
        'hash' => $hash,
        'status' => 'pending',
    ]);
    
    return $document;
}
```

**Result**: Document record exists but hasn't been processed yet.

---

### 4️⃣ PIPELINE PROCESSING INITIATED

**Location**: `app/Services/Pipeline/DocumentProcessingPipeline.php`

**What happens**:
- Get campaign's pipeline configuration
- Create DocumentJob (snapshot of pipeline config)
- Dispatch job to queue
- Return immediately (async)

**Code**:
```php
public function process(Document $document, Campaign $campaign): DocumentJob
{
    // 1. Get campaign's pipeline config
    $pipelineConfig = $campaign->pipeline_config;
    // Looks like:
    // {
    //   "processors": [
    //     {"id": "ocr", "type": "OcrProcessor", "config": {...}},
    //     {"id": "classification", "type": "ClassificationProcessor", ...},
    //     ...
    //   ]
    // }
    
    // 2. Create DocumentJob (SNAPSHOT of pipeline at this moment)
    $job = DocumentJob::create([
        'uuid' => (string) Str::uuid(),
        'campaign_id' => $campaign->id,
        'document_id' => $document->id,
        'pipeline_instance' => $pipelineConfig,  // ← SNAPSHOT
        'current_processor_index' => 0,            // ← Start at index 0
        'attempts' => 0,
        'max_attempts' => 3,
        'state' => 'pending',
    ]);
    
    // 3. Dispatch to queue
    $tenantId = TenantContext::current()?->id;
    ProcessDocumentJob::dispatch($job->id, $tenantId);  // ← Async job
    
    return $job;
}
```

**Key Points**:
- `pipeline_instance` is a **snapshot** - if campaign config changes later, this job keeps old config
- `current_processor_index` starts at 0 (first processor)
- Job goes to queue immediately - doesn't execute synchronously

**Database State**:
```
documents table:
  id: 01abc...
  status: 'pending'

document_jobs table:
  id: 01def...
  document_id: 01abc...
  current_processor_index: 0
  pipeline_instance: {...}
  state: 'pending'
```

---

### 5️⃣ JOB SITS IN QUEUE

**Location**: `app/Jobs/Pipeline/ProcessDocumentJob.php`

**What happens**:
- Job is serialized and stored in queue table
- Waiting for worker to pick it up
- Can have up to 3 retry attempts

**Database State**:
```
jobs table (Laravel's queue):
  id: 1
  queue: 'default'
  payload: {"documentJobId": "01def...", "tenantId": "01ghi..."}
  available_at: now()
  reserved_at: NULL  (not being worked on yet)
```

**Timeline**: Could be immediate or delayed depending on workers.

---

### 6️⃣ QUEUE WORKER PICKS UP JOB

**Location**: `app/Jobs/Pipeline/ProcessDocumentJob.php::handle()`

**What happens**:
- Worker dequeues job
- Job middleware runs (`SetTenantContext`)
- Sets up tenant context from DocumentJob
- Calls `handle()` method

**Code**:
```php
public function handle(DocumentProcessingPipeline $pipeline): void
{
    // 1. Load the DocumentJob
    $documentJob = DocumentJob::findOrFail($this->documentJobId);
    
    // 2. Middleware has already set tenant context (via SetTenantContext)
    // All queries now use tenant database
    
    // 3. Update state: pending → queued → running
    $documentJob->state->transitionTo('running');
    $documentJob->update(['started_at' => now()]);
    
    // 4. Execute the current stage
    $continueProcessing = $pipeline->executeNextStage($documentJob);
    
    // 5. If more stages, re-dispatch job
    if ($continueProcessing) {
        ProcessDocumentJob::dispatch($documentJob);  // ← Queue for next stage
    }
}
```

**Key Point**: Each processor runs in a separate job dispatch. So if you have 4 processors:
- Job 1: Run processor 0 (OCR)
- Job 2: Run processor 1 (Classification)
- Job 3: Run processor 2 (Extraction)
- Job 4: Run processor 3 (Validation)

---

### 7️⃣ EXECUTE NEXT PROCESSOR STAGE

**Location**: `app/Services/Pipeline/DocumentProcessingPipeline.php::executeNextStage()`

**What happens**:
- Get current processor from pipeline config
- Look up processor from registry
- Execute processor
- Handle result (success/failure/retry)
- Advance to next processor

**Code**:
```php
public function executeNextStage(DocumentJob $job): bool
{
    $pipeline = $job->pipeline_instance;
    $processors = $pipeline['processors'];
    $currentIndex = $job->current_processor_index;
    
    // 1. Check if all stages completed
    if ($currentIndex >= count($processors)) {
        $this->completeProcessing($job);
        return false;  // ← No more processors
    }
    
    // 2. Get current processor config
    $processorConfig = $processors[$currentIndex];
    $processorId = $processorConfig['type'];  // 'ocr', 'classification', etc.
    
    // 3. Get processor implementation from registry
    $processor = $this->registry->get($processorId);  // Get OcrProcessor instance
    
    // 4. Look up Processor model (database record)
    $processorModel = Processor::where('category', $processorId)->first();
    
    // 5. Create ProcessorExecution record (tracking this run)
    $execution = ProcessorExecution::create([
        'job_id' => $job->id,
        'processor_id' => $processorModel->id,
        'input_data' => $job->document->metadata ?? [],
        'status' => 'pending',
    ]);
    
    // 6. Fire event
    event(new ProcessorExecutionStarted($execution, $job));
    
    // 7. Execute processor
    try {
        $result = $processor->handle(
            $job->document,
            $processorConfig['config'],  // Processor-specific config
            [
                'job_id' => $job->id,
                'processor_index' => $currentIndex,
                'previous_outputs' => $job->document->metadata['processor_outputs'] ?? [],
            ]
        );
        
        return $this->handleStageResult($job, $execution, $result, $processorConfig);
    } catch (Throwable $e) {
        $result = ProcessorResult::failed('Exception: ' . $e->getMessage());
        return $this->handleStageResult($job, $execution, $result, $processorConfig);
    }
}
```

**Database State**:
```
processor_executions table:
  id: 1
  job_id: 01def...
  processor_id: 01xyz... (OcrProcessor)
  status: 'pending' → 'completed'
  input_data: {...}
  output_data: {"text": "Invoice #123...", "confidence": 0.95}
  duration_ms: 2345
  started_at: now()
  completed_at: now()
```

---

### 8️⃣ PROCESSOR EXECUTES

**Location**: `app/Processors/OcrProcessor.php` (or any other processor)

**What happens**:
- Processor receives document
- Performs its specific task (OCR, classification, etc.)
- Returns `ProcessorResult` with output
- Takes ~1-10 seconds per document

**Example - OCR Processor**:
```php
class OcrProcessor extends AbstractProcessor
{
    public function handle(Document $document, array $config, array $context): ProcessorResult
    {
        try {
            // 1. Get file from storage
            $filePath = Storage::disk('tenant')->path($document->storage_path);
            
            // 2. Run Tesseract OCR
            $ocr = new TesseractOCR($filePath);
            $text = $ocr
                ->lang($config['language'] ?? 'eng')
                ->run();  // ← This takes 2-5 seconds
            
            // 3. Return result
            return ProcessorResult::successful([
                'text' => $text,
                'confidence' => 0.95,
                'language_detected' => 'en',
                'pages' => 1,
            ]);
        } catch (Throwable $e) {
            return ProcessorResult::failed($e->getMessage());
        }
    }
}
```

**Output Example**:
```json
{
  "text": "INVOICE\nDate: 2025-12-01\nAmount: $100.00",
  "confidence": 0.95,
  "language_detected": "en",
  "pages": 1
}
```

---

### 9️⃣ HANDLE PROCESSOR RESULT

**Location**: `app/Services/Pipeline/DocumentProcessingPipeline.php::handleStageResult()`

**What happens**:
- If success: save output, advance to next processor
- If failure: check if can retry, otherwise mark failed

**Code**:
```php
private function handleStageResult(
    DocumentJob $job,
    ProcessorExecution $execution,
    ProcessorResult $result,
    array $processorConfig
): bool {
    if ($result->isSuccess()) {
        // ✅ SUCCESS PATH
        
        // 1. Update ProcessorExecution
        $execution->update([
            'status' => 'completed',
            'output_data' => $result->output,
            'duration_ms' => $result->metadata['duration_ms'] ?? null,
            'completed_at' => now(),
        ]);
        
        // 2. Fire event
        event(new ProcessorExecutionCompleted($execution, $job));
        
        // 3. Store output in document metadata (for next processors)
        $metadata = $job->document->metadata ?? [];
        if (!isset($metadata['processor_outputs'])) {
            $metadata['processor_outputs'] = [];
        }
        $metadata['processor_outputs'][] = [
            'processor' => $processorConfig['type'],
            'output' => $result->output,
        ];
        $job->document->update(['metadata' => $metadata]);
        
        // 4. Advance to next processor
        $job->advanceProcessor();  // current_processor_index++
        $job->save();
        
        // 5. Signal to re-dispatch job for next stage
        return true;  // ← Keep processing
    } else {
        // ❌ FAILURE PATH
        
        // 1. Update ProcessorExecution
        $execution->update([
            'status' => 'failed',
            'error_message' => $result->error,
            'completed_at' => now(),
        ]);
        
        // 2. Check if can retry
        $job->incrementAttempts();
        
        if ($job->canRetry()) {
            // 3. Log error but don't mark job as failed
            Log::warning('Processor failed, retrying...', [
                'processor' => $processorConfig['type'],
                'attempt' => $job->attempts,
            ]);
            
            // Re-dispatch job at same index (will retry same processor)
            return true;  // ← Keep processing (same stage, retry)
        } else {
            // 4. Exhausted retries - final failure
            $this->failProcessing($job, $result->error);
            return false;  // ← Stop processing
        }
    }
}
```

**Key Points**:
- Successful processor output is stored in document metadata
- Next processors can access previous outputs
- On failure, job is retried at same stage
- After 3 failures, job is marked as failed

---

### 🔟 COMPLETE OR RETRY

**Location**: Back in `ProcessDocumentJob::handle()`

**What happens**:
```php
// Back in job handler after executeNextStage() returns

if ($continueProcessing) {
    // Either:
    // 1. More stages to process, OR
    // 2. Retrying failed processor
    
    ProcessDocumentJob::dispatch($documentJob);  // ← Queue next job
} else {
    // Pipeline complete or failed
    if ($documentJob->isCompleted()) {
        // ✅ All stages completed successfully
        event(new DocumentProcessingCompleted($documentJob->campaign, $documentJob->document, $documentJob));
    } else {
        // ❌ Pipeline failed
        event(new DocumentProcessingFailed($documentJob->campaign, $documentJob->document, $documentJob));
    }
}
```

---

## Complete Timeline Example

Let's trace a real document through a 4-processor pipeline:

```
┌─────────────────────────────────────────────────────────────────┐
│ TIME: 12:00:00 - USER UPLOADS PDF                              │
└─────────────────────────────────────────────────────────────────┘
  - File: invoice.pdf (5MB)
  - Campaign: "Invoice Processing" with OCR → Classification → Extraction → Validation
  
  [DocumentProcessingPipeline::process() called]
  - Creates DocumentJob with current_processor_index = 0
  - Dispatches ProcessDocumentJob to queue

┌─────────────────────────────────────────────────────────────────┐
│ TIME: 12:00:01 - JOB #1 STARTS (OCR PROCESSOR)                │
└─────────────────────────────────────────────────────────────────┘
  Queue Worker picks up ProcessDocumentJob
  - DocumentJob.current_processor_index = 0 (OCR)
  - Calls pipeline.executeNextStage()
  
  OcrProcessor.handle() runs:
  - Reads invoice.pdf from tenant storage
  - Runs Tesseract OCR: "INVOICE #123...\nDate: 2025-12-01"
  - Returns ProcessorResult with text + confidence
  
  Result: SUCCESS ✅
  - ProcessorExecution record created
  - Output stored in document.metadata.processor_outputs[0]
  - DocumentJob.current_processor_index advanced to 1
  - ProcessDocumentJob dispatched again

┌─────────────────────────────────────────────────────────────────┐
│ TIME: 12:00:05 - JOB #2 STARTS (CLASSIFICATION PROCESSOR)      │
└─────────────────────────────────────────────────────────────────┘
  Queue Worker picks up ProcessDocumentJob
  - DocumentJob.current_processor_index = 1 (Classification)
  - Calls pipeline.executeNextStage()
  
  ClassificationProcessor.handle() runs:
  - Uses previous output (OCR text)
  - Calls OpenAI API: "Classify this invoice..."
  - API returns: "Invoice" (category), confidence 0.98
  
  Result: SUCCESS ✅
  - Output stored in document.metadata.processor_outputs[1]
  - DocumentJob.current_processor_index advanced to 2
  - ProcessDocumentJob dispatched again

┌─────────────────────────────────────────────────────────────────┐
│ TIME: 12:00:10 - JOB #3 STARTS (EXTRACTION PROCESSOR)          │
└─────────────────────────────────────────────────────────────────┘
  Queue Worker picks up ProcessDocumentJob
  - DocumentJob.current_processor_index = 2 (Extraction)
  
  ExtractionProcessor.handle() runs:
  - Uses OCR + Classification outputs
  - Extracts: invoice_number, date, total_amount
  
  Result: SUCCESS ✅
  - Output stored
  - DocumentJob.current_processor_index advanced to 3
  - ProcessDocumentJob dispatched again

┌─────────────────────────────────────────────────────────────────┐
│ TIME: 12:00:15 - JOB #4 STARTS (VALIDATION PROCESSOR)          │
└─────────────────────────────────────────────────────────────────┘
  Queue Worker picks up ProcessDocumentJob
  - DocumentJob.current_processor_index = 3 (Validation)
  
  ValidationProcessor.handle() runs:
  - Validates extracted data (invoice_number format, date valid, etc.)
  - All validations pass
  
  Result: SUCCESS ✅
  - Output stored
  - DocumentJob.current_processor_index advanced to 4
  - ProcessDocumentJob dispatched again

┌─────────────────────────────────────────────────────────────────┐
│ TIME: 12:00:20 - JOB #5 COMPLETES                              │
└─────────────────────────────────────────────────────────────────┘
  Queue Worker picks up ProcessDocumentJob
  - DocumentJob.current_processor_index = 4
  - pipeline.executeNextStage() checks: 4 >= 4 (number of processors)
  
  Result: ALL STAGES COMPLETE ✅
  - DocumentJob.state → 'completed'
  - Document.status → 'completed'
  - DocumentJob.completed_at = now()
  - Event: DocumentProcessingCompleted dispatched
  - NO MORE JOBS dispatched

FINAL STATE:
  document.status: 'completed'
  document.metadata:
    processor_outputs:
      [0]: OCR output (text)
      [1]: Classification output (category: 'Invoice')
      [2]: Extraction output (invoice_number, date, amount)
      [3]: Validation output (all_passed: true)
  
  Document is now ready for API consumers to retrieve extracted data!
```

---

## Key Data Structures

### DocumentJob (tracks pipeline execution)
```php
DocumentJob {
    id: "01def123",
    uuid: "abc123-def456",
    campaign_id: "01abc456",
    document_id: "01xyz789",
    
    // Pipeline snapshot (config at time of creation)
    pipeline_instance: {
        "processors": [
            {"type": "ocr", "config": {...}},
            {"type": "classification", "config": {...}},
            ...
        ]
    },
    
    // Current position
    current_processor_index: 2,  // Currently on processor 2
    
    // Retry tracking
    attempts: 0,
    max_attempts: 3,
    
    // State machine
    state: "running",  // pending → queued → running → completed/failed
    
    // Timing
    started_at: "2025-12-01T12:00:01",
    completed_at: "2025-12-01T12:00:20",
    
    // Error tracking
    error_log: [
        {"timestamp": "...", "attempt": 1, "error": "..."}
    ]
}
```

### ProcessorExecution (tracks individual processor run)
```php
ProcessorExecution {
    id: "01exc987",
    job_id: "01def123",
    processor_id: "01proc111",  // Processor model ID
    
    // What went in
    input_data: {
        "filename": "invoice.pdf",
        "previous_outputs": [...]
    },
    
    // Configuration
    config: {
        "language": "eng",
        "confidence_threshold": 0.5
    },
    
    // What came out
    output_data: {
        "text": "INVOICE #123...",
        "confidence": 0.95,
        "pages": 1
    },
    
    // Metrics
    status: "completed",
    duration_ms: 4234,
    tokens_used: 150,
    cost_credits: 5,
    
    // Timing
    started_at: "2025-12-01T12:00:01",
    completed_at: "2025-12-01T12:00:05"
}
```

### Document (the main entity being processed)
```php
Document {
    id: "01xyz789",
    uuid: "xyz789-abc123",
    campaign_id: "01abc456",
    
    original_filename: "invoice.pdf",
    mime_type: "application/pdf",
    size_bytes: 5242880,
    
    // Location
    storage_path: "tenants/01ghi.../documents/2025/12/01xyz...pdf",
    storage_disk: "tenant",
    
    // Integrity
    hash: "sha256_hash_here",
    
    // State
    status: "completed",  // pending → processing → completed/failed
    
    // Accumulated results
    metadata: {
        processor_outputs: [
            {processor: "ocr", output: {...}},
            {processor: "classification", output: {...}},
            {processor: "extraction", output: {...}},
        ],
        user_tags: ["important"],
        custom_field: "value"
    },
    
    // Relationships
    campaign: Campaign,
    document_job: DocumentJob,
    
    // Timestamps
    created_at: "2025-12-01T12:00:00",
    updated_at: "2025-12-01T12:00:20"
}
```

---

## Three Proposed Quick Wins Integration

Now you understand the pipeline! Here's where the 3 quick wins fit:

### 1. Progress Tracking
```php
// After each processor completes:
PipelineProgress::updateOrCreate(['job_id' => $job->id], [
    'percentage_complete' => ($job->current_processor_index / count($processors)) * 100,
    'stage_count' => count($processors),
    'completed_stages' => $job->current_processor_index,
    'current_stage' => $processors[$job->current_processor_index]['type'] ?? null,
]);

// API endpoint to query progress
GET /api/documents/{uuid}/progress
{
    "status": "processing_stage_2_of_4",
    "percentage_complete": 50,
    "completed_stages": 2,
    "current_stage": "classification",
    "eta_seconds": 15
}
```

### 2. Processor Hooks
```php
// Before/after each processor execution:
$this->hookManager->beforeExecution($processor, $document, $config);

try {
    $result = $processor->handle(...);
    $this->hookManager->afterExecution($processor, $result);
} catch (Throwable $e) {
    $this->hookManager->onFailure($processor, $e);
    throw;
}

// Built-in hooks:
- MetricsHook: Track time, memory, tokens
- LoggingHook: Structured logging
- CostTrackingHook: Calculate API costs
```

### 3. Output Validation
```php
// After processor returns, validate output:
if ($result->isSuccess()) {
    $validator = new JsonSchemaValidator();
    
    try {
        $validator->validate(
            $result->output,
            $processor->getOutputSchema()  // {"text": string, "confidence": number, ...}
        );
    } catch (ValidationException $e) {
        // Invalid output - mark as failed
    }
}
```

---

## Summary

The pipeline is **event-driven, asynchronous, and tenant-aware**:

1. **User uploads document** → Tenant middleware initializes
2. **Pipeline.process()** creates DocumentJob snapshot
3. **ProcessDocumentJob** dispatched to queue
4. **Queue worker** executes one processor per job
5. **Processor executes** (OCR, Classification, etc.)
6. **Result handled** - success advances, failure retries or fails
7. **Repeat** until all processors complete
8. **Document marked complete** with all processor outputs stored

Each component is **modular, testable, and extensible**. Adding new processors is as simple as creating a class implementing `ProcessorInterface`.

---

## Questions for Your Quick Win Implementation

1. **Progress Tracking**: Do you want ETA calculation based on historical timings?
2. **Processor Hooks**: Which metrics matter most (time, cost, tokens)?
3. **Output Validation**: Should validation failure skip to next processor or fail entire job?

Ready to implement the 3 quick wins?
