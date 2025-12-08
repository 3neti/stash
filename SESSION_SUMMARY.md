# Session Summary: dev.sh Workflow Restoration

**Date**: December 8, 2024  
**Objective**: Restore dev.sh script functionality after tenant auto-onboarding refactor

## Problem Statement

The `dev.sh` script was broken after implementing auto-creation of campaigns via TenantObserver. Previously used `migrate:fresh --seed` to create tenants and campaigns. Now campaigns are dynamically created from templates in `campaigns/templates/` but the workflow wasn't completing successfully.

## Issues Fixed

### 1. **Processor Slug Inconsistency**
- **Problem**: Templates used hyphens (`ekyc-verification`) but seeder had no hyphens (`ekycverification`)
- **Solution**: Standardized all processor slugs to use hyphens in `ProcessorSeeder.php`
- **Changed slugs**: `ekyc-verification`, `electronic-signature`, `s3-storage`, `email-notifier`

### 2. **ProcessorRegistry Not Loading Database Slugs**
- **Problem**: `ImportCampaign` validated against registry's autodiscovered short names (e.g., `ocr`) instead of database slugs
- **Solution**: Added `$registry->registerFromDatabase()` in `ImportCampaign::handle()` to load tenant database processor slugs before validation

### 3. **Fixed Document UUID for Testing**
- **Problem**: Document UUID changed on every run, breaking constant callback URL
- **Solution**: Added `FIXED_DOCUMENT_UUID` support in `UploadDocument.php` (similar to `HYPERVERGE_FIXED_TRANSACTION_IDS`)
- **Constant URL**: `http://stash.test/kyc/callback/e2ed9386-2fef-470a-bfa0-66e3c8e78f3f?transactionId=EKYC-1764773764-3863&status=auto_approved`

### 4. **Placeholder Resolution Not Working**
- **Problem**: `{{ekyc-step.transaction_id}}` wasn't being resolved in electronic signature config
- **Root cause**: Workflow passed flat array of results, but placeholders used step_id as keys
- **Solution**: 
  - Modified `DocumentProcessingWorkflow` to index results by `step_id` instead of numeric index
  - Updated `GenericProcessorActivity::resolveConfigPlaceholders()` to look up by step_id directly

### 5. **KYC Transaction Not Being Registered**
- **Problem**: Callback URL returned "Transaction not found in registry"
- **Root cause**: Slug check was `ekyc-verification` but needed to be `ekyc-verification` (with hyphen)
- **Solution**: Fixed after slug standardization in issue #1

### 6. **Campaign Import Errors Not Logged**
- **Problem**: Template import failures were silently caught
- **Solution**: Added error logging and exit code checking in `ApplyDefaultTemplates::applyTemplate()`

### 7. **Document Signed Event Not Broadcasting**
- **Problem**: Browser didn't receive real-time notification when document was signed
- **Solution**: Added `DocumentSignedEvent` dispatch in `ElectronicSignatureProcessor::process()`
- **Broadcast**: Channel `kyc.{transactionId}`, Event `document.signed`

## Final Working Flow

### Terminal Commands

**Terminal 1**: Dev server
```bash
php artisan optimize:clear && npm run dev
```

**Terminal 2**: Broadcasting (Reverb)
```bash
php artisan reverb:start --debug
```

**Terminal 3**: Setup + Queue worker
```bash
truncate -s0 storage/logs/laravel.log && \
php artisan migrate:fresh && \
php artisan tenant:create "Default Organization" --slug=default --email=admin@example.com && \
sleep 1 && \
php artisan campaign:list --show-processors && \
php artisan queue:work
```

**Terminal 4**: Process document
```bash
php artisan document:process ~/Downloads/Invoice.pdf --campaign=e-signature-workflow --wait --show-output
```

**Browser**: Invoke callback
```
http://stash.test/kyc/callback/e2ed9386-2fef-470a-bfa0-66e3c8e78f3f?transactionId=EKYC-1764773764-3863&status=auto_approved
```

## Verification

✅ Tenant created with auto-onboarding  
✅ Campaigns imported from templates (`e-signature-workflow`, `simple-storage`)  
✅ Processors seeded with correct slugs  
✅ Document uploaded with fixed UUID  
✅ eKYC processor executed with fixed transaction ID  
✅ KYC transaction registered in central database  
✅ Workflow paused waiting for callback  
✅ Callback received and workflow resumed  
✅ Placeholder `{{ekyc-step.transaction_id}}` resolved correctly  
✅ Electronic signature processor completed  
✅ `DocumentSignedEvent` broadcast via Reverb  
✅ Verification URL works: `http://stash.test/documents/verify/{uuid}/{transaction_id}`  

## Known Issues (TODOs)

1. **Signed document download shows 0 KB** - Media record created but file not stored properly
2. **`{{signer.email}}` placeholder not resolving** - Email notifier shows literal placeholder

## Key Files Modified

- `database/seeders/ProcessorSeeder.php` - Fixed processor slugs
- `app/Actions/Campaigns/ImportCampaign.php` - Added registry loading from database
- `app/Actions/Documents/UploadDocument.php` - Added fixed UUID support
- `app/Workflows/DocumentProcessingWorkflow.php` - Changed results to associative array by step_id
- `app/Workflows/Activities/GenericProcessorActivity.php` - Fixed placeholder resolution
- `app/Actions/Campaigns/ApplyDefaultTemplates.php` - Added error logging
- `app/Processors/ElectronicSignatureProcessor.php` - Added DocumentSignedEvent broadcast
- `campaigns/templates/*.yaml` - Updated processor types to use hyphenated slugs
- `app/Console/Commands/CampaignListCommand.php` - Created for verification

## Environment Variables

```env
FIXED_DOCUMENT_UUID=e2ed9386-2fef-470a-bfa0-66e3c8e78f3f
HYPERVERGE_FIXED_TRANSACTION_IDS=EKYC-1764773764-3863
QUEUE_CONNECTION=redis
```

## Next Steps

1. Debug signed PDF file storage issue
2. Add 'signer' context to placeholder resolution or update email template
3. Consider campaign-level broadcast configuration (future enhancement)
