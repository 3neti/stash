# Phase 3: Webhook Handling - COMPLETE ✅

## Summary

Phase 3 has been successfully implemented. The HyperVerge webhook integration for Stash is now complete with custom webhook handling that:
- Finds ProcessorExecution by transaction_id
- Updates execution records with KYC approval/rejection results
- Creates and links Contact records
- Stores KYC images in media collections
- Resumes document processing workflow after approval

## What Was Implemented

### 1. Custom Webhook Job (`app/Jobs/ProcessHypervergeWebhook.php`)

Extends the base `ProcessHypervergeWebhookJob` from `3neti/hyperverge` package with Stash-specific logic:

**Key Methods**:
- `findModelForTransaction(string $transactionId)`: Finds ProcessorExecution or Contact by transaction ID
- `handleApproved(Model $model, KYCResultData $result)`: Processes approved KYC results
- `handleRejected(Model $model, KYCResultData $result, array $reasons)`: Processes rejected KYC results
- `linkToContact(ProcessorExecution $execution, KYCResultData $result)`: Links execution to Contact via contactables pivot
- `resumeDocumentProcessing(ProcessorExecution $execution)`: Dispatches ProcessorExecutionCompleted event

**Features**:
- Dual model support: handles both ProcessorExecution and standalone Contact workflows
- Extracts KYC verification data: face_match_score, liveness_score, extracted ID data
- Idempotent: won't create duplicate contacts
- Logs all major operations for debugging

### 2. Webhook Route Configuration

**Route** (`routes/web.php`):
```php
Route::post('/webhooks/hyperverge', function (\Illuminate\Http\Request $request) {
    $webhookConfig = new \Spatie\WebhookClient\WebhookConfig([
        'name' => 'hyperverge',
        'signing_secret' => config('hyperverge.webhook.secret'),
        'signature_header_name' => 'X-HyperVerge-Signature',
        'signature_validator' => \LBHurtado\HyperVerge\Webhooks\HypervergeSignatureValidator::class,
        'webhook_profile' => \LBHurtado\HyperVerge\Webhooks\HypervergeWebhookProfile::class,
        'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
        'process_webhook_job' => config('hyperverge.webhook.process_webhook_job'),
    ]);

    return (new \Spatie\WebhookClient\WebhookController)->__invoke($request, $webhookConfig);
})->name('webhooks.hyperverge');
```

**Config** (`config/hyperverge.php`):
```php
'webhook' => [
    'secret' => env('HYPERVERGE_WEBHOOK_SECRET'),
    'process_webhook_job' => \App\Jobs\ProcessHypervergeWebhook::class,
],
```

### 3. Dependencies Installed

**New Package**:
- `spatie/laravel-webhook-client` (^3.3) - Handles webhook verification and queuing

**Migrations**:
- `create_webhook_calls_table` - Stores incoming webhooks for processing

### 4. Event Integration

**Event** (`app/Events/ProcessorExecutionCompleted.php`):
- Already existed in Stash
- Dispatched by webhook handler to resume workflow
- Workflow system listens for this event to trigger next processor in pipeline

### 5. Test Suite (`tests/Feature/DeadDrop/ProcessHypervergeWebhookTest.php`)

**Test Coverage**:
- ✅ Webhook route accepts POST requests
- ✅ Finds ProcessorExecution by transaction_id
- ✅ Fallback to Contact when execution not found
- ✅ Returns null for nonexistent transactions
- ✅ Updates ProcessorExecution with approval data (kyc_status, kyc_result, approved_at)
- ✅ Creates and links Contact to ProcessorExecution
- ✅ Updates existing Contact instead of creating duplicates
- ✅ Updates ProcessorExecution with rejection data (kyc_status, rejection_reasons, rejected_at)
- ✅ Updates Contact KYC status
- ✅ Updates Contact with rejection reasons

**Total Tests**: 11 tests covering all major webhook handling scenarios

## Data Flow

### Approval Flow

```
1. HyperVerge sends webhook → `/webhooks/hyperverge`
2. Spatie webhook-client verifies signature → queues ProcessHypervergeWebhook job
3. Job finds ProcessorExecution by transaction_id in output_data JSON column
4. Job updates ProcessorExecution.output_data with:
   - kyc_status: 'approved'
   - kyc_result: { application_status, face_match_score, liveness_score, name, birth_date, id_number }
   - approved_at: ISO8601 timestamp
5. Job calls StoreKYCImages::run() to save ID cards and selfie to media collections
6. Job finds/creates Contact by mobile number (formatted)
7. Job links Contact to ProcessorExecution via contactables pivot (relationship_type: 'signer')
8. Job updates ProcessorExecution.output_data['contact_id']
9. Job dispatches ProcessorExecutionCompleted event
10. Workflow system resumes → dispatches next processor in pipeline
```

### Rejection Flow

```
1. HyperVerge sends webhook → `/webhooks/hyperverge`
2. Spatie webhook-client verifies signature → queues ProcessHypervergeWebhook job
3. Job finds ProcessorExecution by transaction_id
4. Job updates ProcessorExecution.output_data with:
   - kyc_status: 'rejected'
   - rejection_reasons: ['reason1', 'reason2', ...]
   - rejected_at: ISO8601 timestamp
5. Job updates Contact (if exists) with rejection data
6. Workflow halts (no ProcessorExecutionCompleted event)
```

## Configuration

### Environment Variables

```env
# HyperVerge API (already configured in Phase 2)
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_APP_ID=hgqa2f
HYPERVERGE_APP_KEY=omr7u46kwhrjf8ot96j4
HYPERVERGE_URL_WORKFLOW=workflow_2nQDNT

# NEW: Webhook secret (optional, for signature validation)
HYPERVERGE_WEBHOOK_SECRET=your_webhook_secret_here
```

### Webhook URL for HyperVerge Dashboard

```
https://your-domain.com/webhooks/hyperverge
```

Configure this URL in your HyperVerge dashboard under "Webhook Settings".

## Media Collections

ProcessorExecution now stores KYC images in these collections:
- `kyc_id_cards` - ID card images (front/back)
- `kyc_selfies` - Selfie images
- `signature_marks` - Signature images (future)
- `signed_documents` - Signed document PDFs (future)
- `blockchain_timestamps` - Blockchain proof receipts (future)

## Database Schema

### ProcessorExecutioncontactables Pivot

```
contactables (morphMany)
├── contactable_id (ULID) - ProcessorExecution ID
├── contactable_type - 'App\Models\ProcessorExecution'
├── contact_id (ULID) - Contact ID
├── relationship_type (string) - 'signer', 'witness', 'notary', etc.
├── metadata (JSON) - { kyc_result, transaction_id, signed_at, etc. }
└── timestamps
```

### ProcessorExecution.output_data Structure (After Webhook)

```json
{
  "transaction_id": "ekyc_01xyz_01abc_1733223456_abc123",
  "kyc_link": "https://ind.idv.hyperverge.co/ekyc/...",
  "kyc_status": "approved",
  "kyc_result": {
    "application_status": "auto_approved",
    "face_match_score": 95.5,
    "liveness_score": 98.2,
    "name": "Juan Dela Cruz",
    "birth_date": "1990-01-15",
    "id_number": "ABC123456"
  },
  "contact_id": "01xyz...",
  "contact_mobile": "+639171234567",
  "contact_name": "Juan Dela Cruz",
  "contact_email": "juan@example.com",
  "approved_at": "2025-12-03T17:45:30+08:00"
}
```

## Next Steps

### Phase 4: End-to-End Testing with `document:process`

Now that all three phases are complete:
1. ✅ Phase 1: Contact Package Integration
2. ✅ Phase 2: EKycVerificationProcessor Creation
3. ✅ Phase 3: Webhook Handling

We can now test the full workflow:

```bash
# 1. Create test campaign with ekyc-verification processor
php artisan tinker
> $campaign = Campaign::create(['name' => 'Test eKYC Campaign', ...]);
> $campaign->processors()->attach($processor->id, ['order' => 1, 'config' => [...]]);

# 2. Upload document and trigger processing
php artisan document:process /path/to/test-document.pdf \
    --campaign=test-ekyc-campaign \
    --wait \
    --show-output

# 3. Simulate webhook callback (or use HyperVerge test link)
curl -X POST http://stash.test:8000/webhooks/hyperverge \
  -H "Content-Type: application/json" \
  -d '{
    "transactionId": "ekyc_01xyz_01abc_1733223456_abc123",
    "applicationStatus": "auto_approved"
  }'

# 4. Verify results
php artisan tinker
> $execution = ProcessorExecution::latest()->first();
> dump($execution->output_data);
> dump($execution->contacts);
> dump($execution->getMedia('kyc_id_cards'));
```

## Files Modified/Created

### New Files
- `app/Jobs/ProcessHypervergeWebhook.php` (260 lines)
- `tests/Feature/DeadDrop/ProcessHypervergeWebhookTest.php` (391 lines)
- `PHASE_3_WEBHOOK_COMPLETE.md` (this file)

### Modified Files
- `routes/web.php` - Added webhook route
- `config/hyperverge.php` - Added webhook configuration
- `composer.json` - Added spatie/laravel-webhook-client dependency

### Database
- Migration published: `2025_12_03_094453_create_webhook_calls_table.php`

## Success Criteria

✅ **All Phase 3 objectives met**:
1. ✅ Custom webhook job created extending base hyperverge job
2. ✅ findModelForTransaction() implemented to search ProcessorExecution by transaction_id
3. ✅ handleApproved() updates execution, stores images, links contact, resumes workflow
4. ✅ handleRejected() updates execution with rejection data
5. ✅ Contact creation/linking via contactables pivot implemented
6. ✅ StoreKYCImages action integrated for media storage
7. ✅ ProcessorExecutionCompleted event dispatched to resume workflow
8. ✅ Webhook route registered with proper configuration
9. ✅ Comprehensive test suite created (11 tests)
10. ✅ spatie/laravel-webhook-client installed and configured

## Troubleshooting

### Webhook not processing
1. Check queue worker is running: `php artisan queue:work`
2. Check webhook_calls table for incoming webhooks
3. Check failed_jobs table for errors
4. Tail logs: `php artisan pail | grep HyperVerge`

### Transaction not found
1. Verify transaction_id format matches EKycVerificationProcessor output
2. Check ProcessorExecution.output_data in database
3. Verify processor slug is 'ekyc-verification'

### Contact not linking
1. Verify mobile number format (should be E.164: +639171234567)
2. Check contactables pivot table
3. Verify execution has contact_mobile in output_data

## Production Checklist

Before going live:
- [ ] Set HYPERVERGE_WEBHOOK_SECRET in production .env
- [ ] Configure webhook URL in HyperVerge dashboard
- [ ] Set up queue workers with supervisor/systemd
- [ ] Enable queue monitoring (Horizon)
- [ ] Set up log aggregation for webhook processing
- [ ] Test webhook with HyperVerge test environment
- [ ] Verify media storage permissions for KYC images
- [ ] Set up database backups for webhook_calls table
- [ ] Configure webhook retry strategy (default: 3 attempts)
- [ ] Monitor webhook latency and processing times

---

**Status**: ✅ COMPLETE
**Duration**: ~2 hours
**Lines of Code**: ~651 lines (job + tests + config)
**Tests**: 11 comprehensive tests
**Ready for**: Phase 4 - End-to-End Integration Testing
