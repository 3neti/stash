# Laravel Workflow Signal Pattern for KYC Callbacks

This document explains how the workflow signal pattern enables asynchronous KYC verification with Laravel Workflow.

## Overview

The KYC verification workflow demonstrates true async processing: the workflow pauses after initiating eKYC verification, waits for the user to complete verification in their browser, and automatically resumes when the callback arrives - all without blocking queue workers or losing state.

## Architecture

```
DocumentProcessingWorkflow
  ↓
GenericProcessorActivity (eKYC)
  ↓ (registers workflow_id in KYC transaction)
Workflow pauses with awaitWithTimeout()
  ↓ (waiting for signal...)
User completes KYC in browser
  ↓
Callback arrives → FetchKycDataFromCallback job
  ↓
Workflow resumed via receiveKycCallback() signal
  ↓
Workflow continues to next processors
```

## Key Components

### 1. Workflow Signal Method

The workflow defines a signal method that external code can invoke:

```php
// app/Workflows/DocumentProcessingWorkflow.php

use Workflow\SignalMethod;
use Workflow\WorkflowStub;

class DocumentProcessingWorkflow extends Workflow
{
    private array $callbackSignals = [];

    #[SignalMethod]
    public function receiveKycCallback(string $transactionId, array $callbackData): void
    {
        $this->callbackSignals[$transactionId] = $callbackData;
    }

    public function execute(string $documentJobId, string $tenantId)
    {
        // Execute processor
        $result = yield ActivityStub::make(GenericProcessorActivity::class, ...);
        
        // Check if awaiting callback
        if ($result['awaiting_callback'] ?? false) {
            $transactionId = $result['transaction_id'];
            
            // Pause workflow and wait for signal (24-hour timeout)
            $signalReceived = yield WorkflowStub::awaitWithTimeout(
                86400, // 24 hours in seconds
                fn() => isset($this->callbackSignals[$transactionId])
            );
            
            if ($signalReceived) {
                // Get callback data from signal
                $callbackData = $this->callbackSignals[$transactionId];
                // Continue processing...
            } else {
                // Timeout - no callback received
                throw new \Exception("KYC callback timeout");
            }
        }
    }
}
```

### 2. Workflow ID Registration

Activities capture the workflow ID and store it for later signaling:

```php
// app/Workflows/Activities/GenericProcessorActivity.php

public function execute(...): array
{
    // Get workflow ID from activity context
    $rawWorkflowId = $this->workflowId();
    $workflowId = $rawWorkflowId ? (string) $rawWorkflowId : null;
    
    // Register KYC transaction with workflow_id
    if ($processorModel->slug === 'ekyc-verification') {
        $this->registerKycTransaction(
            transactionId: $result->output['transaction_id'],
            workflowId: $workflowId,
            documentJobId: $documentJob->id,
            // ...
        );
    }
}

protected function registerKycTransaction(...): void
{
    KycTransaction::updateOrCreate(
        ['transaction_id' => $transactionId],
        [
            'workflow_id' => $workflowId,
            'document_job_id' => $documentJobId,
            'tenant_id' => $tenantId,
            // ...
        ]
    );
}
```

### 3. Signal Sending

The callback job signals the workflow to resume:

```php
// app/Jobs/FetchKycDataFromCallback.php

protected function signalWorkflowToContinue(
    KycTransaction $kycTransaction,
    ?Contact $contact
): void {
    // Load the workflow by ID
    $workflow = WorkflowStub::load($kycTransaction->workflow_id);
    
    // Call the signal method
    $callbackData = [
        'kyc_status' => 'approved',
        'contact_id' => $contact?->id,
        'kyc_completed_at' => now()->toIso8601String(),
    ];
    
    $workflow->receiveKycCallback($this->transactionId, $callbackData);
}
```

## Database Schema

### KYC Transactions Table

```sql
-- database/migrations/2025_12_03_222414_create_kyc_transactions_table.php

CREATE TABLE kyc_transactions (
    id SERIAL PRIMARY KEY,
    transaction_id VARCHAR UNIQUE,
    tenant_id ULID REFERENCES tenants(id),
    document_id ULID,
    processor_execution_id ULID,
    workflow_id VARCHAR(26), -- Laravel Workflow database ID
    document_job_id VARCHAR(26), -- DocumentJob ULID
    status VARCHAR DEFAULT 'pending',
    metadata JSON,
    callback_received_at TIMESTAMP,
    webhook_received_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX (workflow_id),
    INDEX (document_job_id)
);
```

**Important**: The `workflow_id` and `document_job_id` fields must be in the model's `$fillable` array:

```php
// app/Models/KycTransaction.php

protected $fillable = [
    'transaction_id',
    'tenant_id',
    'document_id',
    'processor_execution_id',
    'workflow_id',        // ✅ Required for mass assignment
    'document_job_id',    // ✅ Required for mass assignment
    'status',
    'metadata',
    'callback_received_at',
    'webhook_received_at',
];
```

## Workflow Execution Flow

### Step 1: Document Upload
```bash
php artisan document:process invoice.pdf --campaign=test-ekyc-campaign
```

### Step 2: Workflow Starts
- GenericProcessorActivity executes EKycVerificationProcessor
- Processor returns `awaiting_callback: true` and `transaction_id`
- Activity captures `workflowId()` → `"1"` (database ID)
- KYC transaction registered with `workflow_id: "1"`

### Step 3: Workflow Pauses
```
[Workflow] Waiting for external callback signal
  transaction_id: EKYC-1764773764-3863
  document_job_id: 01kbm...
```

Workflow uses `WorkflowStub::awaitWithTimeout()` and enters "waiting" state.

### Step 4: User Completes KYC
User navigates to `http://stash.test/kyc/callback/{uuid}?transactionId=EKYC-...&status=auto_approved`

### Step 5: Callback Processing
- `FetchKycDataFromCallback` job dispatched
- Fetches KYC data from HyperVerge
- Creates/updates Contact with personal data
- **Signals workflow**: `$workflow->receiveKycCallback($txId, $data)`

### Step 6: Workflow Resumes
```
[Workflow] receiveKycCallback method called
  transaction_id: EKYC-1764773764-3863
  callback_data: {kyc_status: "approved", contact_id: "01kbm..."}

[Workflow] Callback signal processed
```

Workflow continues to next processors (if any) or completes.

## Testing

### Unit Tests

```php
// tests/Feature/Workflows/DocumentProcessingWorkflowSignalTest.php

test('kyc transaction has workflow_id and document_job_id columns', function () {
    $columns = DB::connection('central')
        ->getSchemaBuilder()
        ->getColumnListing('kyc_transactions');
    
    expect($columns)->toContain('workflow_id')
        ->and($columns)->toContain('document_job_id');
});

test('kyc transaction model allows mass assignment of workflow fields', function () {
    $transaction = KycTransaction::make([
        'workflow_id' => '1',
        'document_job_id' => '01jkl',
        // ...
    ]);
    
    expect($transaction->workflow_id)->toBe('1');
});
```

### Integration Test

```bash
# Terminal 1: Start queue worker
php artisan queue:work

# Terminal 2: Process document
php artisan document:process ~/invoice.pdf --campaign=test-ekyc-campaign --wait

# Terminal 3: Check workflow is waiting
grep "Waiting for external callback" storage/logs/laravel.log

# Browser: Complete KYC verification
# Visit: http://stash.test/kyc/callback/{uuid}?transactionId=EKYC-...&status=auto_approved

# Terminal 3: Verify workflow resumed
grep "Workflow signaled\|receiveKycCallback\|Callback signal processed" storage/logs/laravel.log
```

Expected output:
```
[FetchKycData] Workflow signaled to continue
[Workflow] receiveKycCallback method called
[Workflow] Callback signal processed
```

## Monitoring

### Waterline Dashboard
Monitor workflow state in real-time:
```
http://stash.test:8000/waterline
```

Workflow states:
- **Running** - Actively executing activities
- **Waiting** - Paused, waiting for signal
- **Completed** - Successfully finished
- **Failed** - Error occurred or timeout

### Logs

Debug logs show the signal flow:

```php
// Activity captures workflow ID
[GenericProcessorActivity] Captured workflow ID
  raw_workflow_id: 1
  workflow_id_string: "1"

// Workflow waits for signal
[Workflow] Waiting for external callback signal
  transaction_id: EKYC-...

// Callback checks workflow_id
[FetchKycData] Checking workflow_id for signaling
  workflow_id: "1"
  has_workflow_id: true

// Signal sent
[FetchKycData] Workflow signaled to continue
  workflow_id: "1"

// Signal received
[Workflow] receiveKycCallback method called
  transaction_id: EKYC-...
  existing_signals: ["EKYC-..."]

// Workflow processes callback
[Workflow] Callback signal processed
```

## Timeout Handling

If no callback arrives within 24 hours:

```php
$signalReceived = yield WorkflowStub::awaitWithTimeout(
    86400, // 24 hours
    fn() => isset($this->callbackSignals[$transactionId])
);

if (!$signalReceived) {
    // Timeout - callback never arrived
    Log::warning('[Workflow] Callback timeout', [
        'transaction_id' => $transactionId,
        'timeout_hours' => 24,
    ]);
    
    throw new \Exception("KYC callback timeout");
}
```

The workflow will fail and can be retried or handled via error events.

## Common Issues

### Issue: `workflow_id` is NULL in database

**Cause**: Missing from `$fillable` array in `KycTransaction` model.

**Solution**: Add to fillable:
```php
protected $fillable = [
    // ...
    'workflow_id',
    'document_job_id',
];
```

### Issue: Signal method not called

**Cause**: `WorkflowStub::find()` doesn't exist (use `load()`).

**Solution**: Use `WorkflowStub::load($id)` to retrieve workflow.

### Issue: Workflow doesn't pause

**Cause**: `awaiting_callback` flag not returned by processor.

**Solution**: Ensure processor returns:
```php
return [
    'transaction_id' => $txId,
    'awaiting_callback' => true,
    // ...
];
```

## References

- [Laravel Workflow Documentation](https://laravel-workflow.com)
- [Signals Documentation](https://laravel-workflow.com/docs/features/signals/)
- [Signal + Timer](https://laravel-workflow.com/docs/features/signal+timer/)
- [Waterline Monitoring](https://github.com/laravel-workflow/waterline)
