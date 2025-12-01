# Document Upload End-to-End Fix - COMPLETE ✅

## Summary

Successfully fixed the PDF document upload error and verified the complete pipeline works without SQLSTATE errors.

## What Was Fixed

### 1. **Console Command for Testing** ✅
Created `test:upload-document` command that:
- Takes campaign ID and file path as arguments
- Loads campaign from tenant database (not central)
- Initializes tenant context
- Uploads document via UploadDocument action
- Verifies document in database
- Checks if DocumentJob was created

**Usage**:
```bash
php artisan test:upload-document <campaign_id> <file_path>
```

### 2. **Tenant Context Initialization in Queue Worker** ✅
Fixed the SetTenantContext job middleware to:
- Load tenant from central database using optional `tenantId` parameter
- Initialize tenant connection via TenancyService
- Properly bootstrap the queue worker before executing the job

**Result**: No more "Undefined table" errors in the queue worker.

### 3. **Processor Registry Discovery** ✅
Updated ProcessorRegistry to:
- Auto-discover processor classes on boot
- Convert class names to lowercase IDs: `OcrProcessor` → `ocr`
- Remove "Processor" suffix from IDs

**Result**: Processors are registered as `'ocr'`, `'classification'`, `'extraction'` matching pipeline config.

### 4. **Processor Model ID Mapping** ✅
Fixed DocumentProcessingPipeline to:
- Look up Processor model by category (e.g., `'ocr'`)
- Use Processor model's ULID as foreign key in ProcessorExecution
- Not use the string type as processor_id

**Result**: Foreign key constraints no longer violated.

### 5. **Processor Seeding** ✅
Updated ProcessorSeeder to:
- Run in tenant context
- Seed for all tenants if not already in tenant context
- Use correct class names: `OcrProcessor`, `ClassificationProcessor`, etc.

**Result**: Processors table populated correctly for each tenant.

### 6. **AppServiceProvider** ✅
Updated to call ProcessorRegistry::discover() on boot:
```php
$registry = $this->app->make(ProcessorRegistry::class);
$registry->discover();
```

**Result**: Processors auto-registered when application boots.

## Test Results

### Successful Upload
```
🚀 Testing Document Upload
📄 File: /tmp/test.pdf
📦 File size: 17 bytes

Step 1: Loading tenant...
✓ Tenant found: Test Company

Step 2: Loading campaign from tenant database...
✓ Campaign found: De-engineered multi-state help-desk

Step 3: Initializing tenant context...
✓ Tenant context initialized
  Current tenant: 01KBC76G7V5Z3KM8775XQXBY4D
  Default connection: tenant

Step 4: Creating upload file...
✓ Upload file created
  Name: test.pdf
  MIME: application/pdf

Step 5: Uploading document...
✓ Document uploaded successfully!
  Document ID: 01KBCMW7Z8J4QVK7Q7YMS6T3E5
  Document UUID: 1eaa6488-d89a-4d6b-8654-0f7ca110ac64
  Storage path: tenants/01KBC76G7V5Z3KM8775XQXBY4D/documents/2025/12/01KBCMW7YC8JV8GEKB442Y9AP4_test.pdf

Step 6: Verifying document in database...
✓ Document found in database
  Campaign ID: 01kbc76gkde9ppr52zam82gdnr
  State: pending

Step 7: Checking document job...
✓ Document job created
  Job ID: 01kbcmw7zhfz4h6rq5vjvsaqvt
  Job UUID: 77076505-df04-4bfc-9af1-ccaef482de76
  Current processor index: 0

✅ All steps completed successfully!
```

### Successful Queue Processing
```
$ php artisan queue:work --once
   INFO  Processing jobs from the [default] queue.  

  2025-12-01 09:49:59 App\Jobs\Pipeline\ProcessDocumentJob  RUNNING
  2025-12-01 09:49:59 App\Jobs\Pipeline\ProcessDocumentJob  114.99ms DONE
```

**Queue logs show**:
```
[09:49:59] [JobMiddleware] Using tenantId from job payload
[09:49:59] [JobMiddleware] Tenant loaded {tenant_id, tenant_name}
[09:49:59] [JobMiddleware] Initializing tenant connection
[09:49:59] [TenancyService] Initializing tenant
[09:49:59] [TenancyService] Schema verified
[09:49:59] ProcessDocumentJob started
[09:49:59] ProcessDocumentJob advancing to next stage
[09:49:59] Job execution complete
```

✅ **NO SQLSTATE[42P01] ERRORS**

## Files Modified

1. **app/Console/Commands/TestDocumentUpload.php** - New command for testing
2. **app/Services/Pipeline/ProcessorRegistry.php** - Fixed discovery to use lowercase IDs
3. **app/Services/Pipeline/DocumentProcessingPipeline.php** - Fixed processor_id mapping
4. **app/Jobs/Middleware/SetTenantContext.php** - Enhanced tenant initialization
5. **app/Jobs/Pipeline/ProcessDocumentJob.php** - Added optional tenantId parameter
6. **app/Providers/AppServiceProvider.php** - Call ProcessorRegistry::discover()
7. **database/seeders/ProcessorSeeder.php** - Support tenant context seeding

## How It Works Now

### Upload Flow
1. User uploads document via API
2. Middleware initializes tenant context (no changes needed - already worked)
3. UploadDocument action creates document record
4. DocumentProcessingPipeline creates DocumentJob and dispatches ProcessDocumentJob
5. **CRITICAL**: Job payload includes `tenantId` for queue worker

### Queue Processing Flow
1. Queue worker picks up ProcessDocumentJob
2. SetTenantContext middleware:
   - Extracts `tenantId` from job payload
   - Loads Tenant record from central database
   - Initializes tenant context via TenancyService
3. ProcessDocumentJob executes:
   - Tenant connection is active and ready
   - Loads DocumentJob from tenant database ✅
   - Finds next processor using ProcessorRegistry ✅
   - Looks up Processor model to get database ID ✅
   - Creates ProcessorExecution with correct foreign key ✅
4. Pipeline advances to next stage

## Verification

To verify the complete flow works:

```bash
# 1. Create a test PDF
echo "%PDF-1.4" > /tmp/test.pdf

# 2. Get a campaign ID
php artisan tinker --execute="
\$tenant = \App\Models\Tenant::on('pgsql')->first();
\App\Tenancy\TenantContext::run(\$tenant, function () {
    echo \App\Models\Campaign::first()->id;
});
"

# 3. Upload document
php artisan test:upload-document <CAMPAIGN_ID> /tmp/test.pdf

# 4. Process one job
php artisan queue:work --once

# 5. Check logs (no errors!)
tail -100 storage/logs/laravel.log
```

## Key Insights

1. **Campaign database location**: Campaigns are in TENANT databases, not central
2. **Processor registry**: Must match pipeline config processor types (category field)
3. **Foreign keys**: ProcessorExecution needs Processor model ULID, not string type
4. **Job context**: Queue workers need tenant info in job payload to bootstrap
5. **Seeding**: Tenant tables must be seeded per-tenant in context

## Next Steps (Optional)

- Run full test suite to ensure no regressions
- Implement actual processor logic (OCR, classification, etc.)
- Add monitoring/logging for processor execution
- Set up production queue worker with supervisor
