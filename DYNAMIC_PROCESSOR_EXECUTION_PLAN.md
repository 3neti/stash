# Dynamic Processor Execution for eKYC Integration

## Problem

The current document processing system uses a hardcoded `DocumentProcessingWorkflow` that calls specific activities (`OcrActivity`, `ClassificationActivity`, `ExtractionActivity`, `ValidationActivity`) in a fixed sequence. This prevents the eKYC processor from running because:
- The workflow doesn't read the campaign's `pipeline_config`
- Activities are hardcoded to process only processors at specific indices (OCR at index 0, Classification at index 1, etc.)
- New processor types (like eKYC verification) can't be integrated without modifying the workflow

## Current Architecture

### How Document Processing Works Now

1. **Document Upload** → `DocumentProcessingPipeline::process()` creates `DocumentJob` with snapshot of campaign's `pipeline_config`
2. **Workflow Start** → `WorkflowStub::make(DocumentProcessingWorkflow::class)->start($jobId, $tenantId)`
3. **Hardcoded Execution** → Workflow calls activities in fixed order:
   - `OcrActivity` (reads processor at index 0)
   - `ClassificationActivity` (reads processor at index 1) 
   - `ExtractionActivity` (reads processor at index 2)
   - `ValidationActivity` (reads processor at index 3)
4. **Activity Pattern** → Each activity:
   - Initializes tenant context
   - Loads DocumentJob and reads `pipeline_instance['processors'][INDEX]`
   - Gets Processor model by ULID from config
   - Registers processor with `ProcessorRegistry` if needed
   - Instantiates processor via registry using `slug`
   - Creates `ProcessorExecution` record
   - Calls `processor->handle($document, $config, $context)`
   - Updates execution with results
   - Fires `ProcessorExecutionCompleted` event
   - Returns results for next activity
5. **Completion** → `WorkflowCompletedListener` marks DocumentJob and Document as completed

### Key Components

- **ProcessorRegistry**: Maps slugs/ULIDs to processor class names, handles instantiation
- **ProcessorExecution**: Tracks each processor invocation with metrics and state
- **Campaign.pipeline_config**: JSON structure with processors array: `[{id: ULID, type: category, config: {...}}]`
- **Processor.category**: Enum with values: ocr, classification, extraction, validation, enrichment, notification, storage, transformation, custom

## Proposed Solution

### Option A: Generic Activity (RECOMMENDED)

Create a single `GenericProcessorActivity` that dynamically executes ANY processor from the campaign pipeline.

**Workflow Change:**
```php
class DocumentProcessingWorkflow extends Workflow
{
    public function execute(string $documentJobId, string $tenantId)
    {
        // Read pipeline_config from DocumentJob
        $processors = $this->getProcessorConfigs($documentJobId, $tenantId);
        $results = [];
        
        // Execute each processor dynamically
        foreach ($processors as $index => $processorConfig) {
            $result = yield ActivityStub::make(
                GenericProcessorActivity::class,
                $documentJobId,
                $index,  // Pass index to activity
                $results,  // Pass previous results
                $tenantId
            );
            
            $results[] = $result;
        }
        
        return $results;
    }
}
```

**Activity Implementation:**
- Reads processor at specified index from `pipeline_instance['processors'][$index]`
- Handles null/skip processors gracefully (if `id` is null)
- Works for ANY processor type (OCR, eKYC, CSV, custom, etc.)
- Maintains same execution pattern as existing activities

**Benefits:**
- ✅ Supports any number of processors
- ✅ Works with eKYC processor immediately
- ✅ No hardcoded processor types
- ✅ Reuses existing ProcessorExecution tracking
- ✅ Compatible with current listener system

**Trade-offs:**
- ⚠️ Workflow needs helper method to read DocumentJob early (before activities start)
- ⚠️ Requires passing index and results between activities

### Option B: Keep Hardcoded Activities, Add eKYC Activity

Add `EKycActivity` as a 5th hardcoded activity, update workflow to call it.

**Not Recommended Because:**
- ❌ Doesn't solve the fundamental problem
- ❌ Every new processor type needs a new activity
- ❌ Pipeline position remains fixed (can't reorder processors)
- ❌ Doesn't scale

## Implementation Plan

### Step 1: Create GenericProcessorActivity

Create `app/Workflows/Activities/GenericProcessorActivity.php`:
- Extend `Workflow\Activity`
- Accept parameters: `$documentJobId`, `$processorIndex`, `$previousResults`, `$tenantId`
- Load DocumentJob and read processor at `pipeline_instance['processors'][$processorIndex]`
- Handle null processor (skip gracefully, return `{skipped: true}`)
- Get Processor model by ULID from config
- Register with ProcessorRegistry if needed
- Instantiate processor
- Create ProcessorExecution record
- Call `processor->handle()` with context including previous results
- Update execution, fire events
- Return results
- Use `HandlesProcessorArtifacts` trait from existing activities

### Step 2: Update DocumentProcessingWorkflow

Modify `app/Workflows/DocumentProcessingWorkflow.php`:
- Add helper method `getProcessorConfigs()` to read pipeline early:
  ```php
  private function getProcessorConfigs(string $documentJobId, string $tenantId): array
  {
      $tenant = Tenant::on('central')->findOrFail($tenantId);
      app(TenancyService::class)->initializeTenant($tenant);
      $job = DocumentJob::findOrFail($documentJobId);
      return $job->pipeline_instance['processors'] ?? [];
  }
  ```
- Update `execute()` method to loop through processors dynamically
- Remove hardcoded activity calls (OcrActivity, ClassificationActivity, etc.)

### Step 3: Test with eKYC Campaign

Run test to verify:
```bash
php artisan document:process ~/Downloads/Invoice.pdf \
  --campaign=test-ekyc-campaign --wait --show-output
```

Expected behavior:
- GenericProcessorActivity executes at index 0
- Reads ekyc-verification processor from config
- Instantiates EKycVerificationProcessor
- Calls `handle()` method
- Generates HyperVerge KYC link
- Stores transaction_id in ProcessorExecution.output_data
- Job waits for webhook (pending state)

### Step 4: Handle Webhook Resume

Current webhook handler already:
- Finds ProcessorExecution by transaction_id
- Updates output_data with KYC results
- Dispatches `ProcessorExecutionCompleted` event

No changes needed - workflow will resume automatically after webhook.

### Step 5: Deprecate Old Activities

Mark as deprecated (keep for backward compatibility):
- `OcrActivity.php`
- `ClassificationActivity.php`
- `ExtractionActivity.php`
- `ValidationActivity.php`

Add deprecation notices in PHPDoc.

## Testing Strategy

### Unit Tests
- Test GenericProcessorActivity with various processor types
- Test null processor handling (graceful skip)
- Test processor registry lookup (ULID vs slug)
- Test context passing (previous results)

### Integration Tests
- Test eKYC campaign end-to-end
- Test CSV import campaign (transformation processor)
- Test mixed processor pipeline (OCR → eKYC → validation)
- Test webhook resume after eKYC approval

### Manual Testing
```bash
# Test eKYC processor
php artisan document:process test.pdf --campaign=test-ekyc-campaign --wait

# Test CSV import
php artisan document:process employees.csv --campaign=employee-csv-import --wait

# Test invoice processing (existing)
php artisan document:process invoice.pdf --campaign=invoice-processing --wait
```

## Files to Modify

### New Files
- `app/Workflows/Activities/GenericProcessorActivity.php` (~200 lines)

### Modified Files  
- `app/Workflows/DocumentProcessingWorkflow.php` (~50 lines modified)
- `app/Workflows/Activities/OcrActivity.php` (add @deprecated)
- `app/Workflows/Activities/ClassificationActivity.php` (add @deprecated)
- `app/Workflows/Activities/ExtractionActivity.php` (add @deprecated)
- `app/Workflows/Activities/ValidationActivity.php` (add @deprecated)

### Test Files
- `tests/Feature/Workflows/GenericProcessorActivityTest.php` (new)
- `tests/Feature/Workflows/DynamicWorkflowExecutionTest.php` (new)

## Rollout Plan

### Phase 1: Create GenericProcessorActivity
Implement the activity with full processor support.

### Phase 2: Update Workflow
Modify DocumentProcessingWorkflow to use GenericProcessorActivity.

### Phase 3: Test
Run comprehensive tests with eKYC, CSV, and existing campaigns.

### Phase 4: Deploy
Deploy to staging, verify webhook integration works.

### Phase 5: Deprecate
Mark old activities as deprecated, plan removal in future release.

## Success Criteria

- ✅ eKYC processor runs via `document:process` command
- ✅ Generates HyperVerge link and stores transaction_id
- ✅ Webhook updates execution and resumes workflow
- ✅ All existing campaigns continue to work
- ✅ CSV import processor works
- ✅ No hardcoded processor assumptions in workflow
- ✅ Tests pass (unit + integration)

## Risks & Mitigation

**Risk**: Early DocumentJob loading in workflow breaks checkpointing  
**Mitigation**: Use helper method only for reading config, not for state mutations

**Risk**: Passing results array grows large with many processors  
**Mitigation**: Only pass necessary data, use document metadata for full context

**Risk**: Old activities break if used directly  
**Mitigation**: Keep them functional, add deprecation notices

**Risk**: ProcessorRegistry lookup fails for new processors  
**Mitigation**: Activity auto-registers processors if not found in registry
