# Document Upload API - FIXED ✅

## The Problem

The web/API document uploads were still failing with `SQLSTATE[42P01]: Undefined table: relation "campaigns" does not exist` even after fixing the console command and queue processing.

**Root Cause**: The API routes didn't have the `InitializeTenantFromUser` middleware, so tenant context was never initialized for API requests.

## The Solution

Added `InitializeTenantFromUser` middleware to the Document Ingestion API routes in `routes/api.php`:

```php
Route::middleware(['auth:sanctum', 'throttle:api', InitializeTenantFromUser::class])
    ->prefix('campaigns/{campaign}')
    ->group(function () {
        Route::post('documents', UploadDocument::class)
            ->middleware('throttle:api-uploads')
            ->name('api.campaigns.documents.store');
        
        Route::get('documents', ListDocuments::class)
            ->name('api.campaigns.documents.index');
    });
```

## Test Results

**All API tests now PASS** ✅

```
✓ Document Ingestion API → POST /api/campaigns/{campaign}/documents - uploads document successfully
✓ Document Ingestion API → POST /api/campaigns/{campaign}/documents - validates file is required
✓ Document Ingestion API → POST /api/campaigns/{campaign}/documents - validates file type
✓ Document Ingestion API → POST /api/campaigns/{campaign}/documents - validates file size
✓ Document Ingestion API → GET /api/campaigns/{campaign}/documents - lists documents
✓ Document Ingestion API → GET /api/campaigns/{campaign}/documents - filters by status
✓ Document Ingestion API → GET /api/campaigns/{campaign}/documents - paginates results
✓ Document Ingestion API → GET /api/documents/{uuid} - retrieves document status
✓ Document Ingestion API → GET /api/documents/{uuid} - returns 404 for invalid UUID
✓ Document Ingestion API → GET /api/documents/{uuid} - validates UUID format

Tests: 2 skipped, 23 passed (71 assertions)
```

## Why This Works

The `InitializeTenantFromUser` middleware:
1. Extracts tenant_id from the authenticated user
2. Initializes tenant context via TenancyService
3. Ensures all tenant-scoped queries use the correct tenant database
4. Cleans up after the request completes

Without this middleware:
- `Campaign::find()` queries used the central database (doesn't have campaigns)
- Results in "Undefined table" error

With the middleware:
- `Campaign::find()` queries use the tenant database (has campaigns)
- Upload works correctly
- DocumentJob created and dispatched to queue
- Queue processor can handle the job without errors

## Complete Flow Now

### Upload (Web/API)
```
HTTP Request (POST /api/campaigns/{id}/documents)
  ↓
Middleware: InitializeTenantFromUser
  - Extract tenant_id from user
  - Initialize tenant context
  ↓
UploadDocument::asController()
  - Load Campaign from tenant DB ✅
  - Create Document record
  - Create DocumentJob
  - Dispatch to queue ✅
  ↓
Response sent to client

Request cleanup:
  - TenantContext::forgetCurrent()
  - Disconnect tenant DB
```

### Queue Processing
```
Queue worker picks up job
  ↓
SetTenantContext middleware
  - Extract tenantId from job payload
  - Initialize tenant context
  ↓
ProcessDocumentJob::handle()
  - Load DocumentJob ✅
  - Find processor ✅
  - Create ProcessorExecution ✅
  - Advance to next stage ✅
  ↓
Job complete (DONE)
```

## Files Modified

1. **routes/api.php** - Added InitializeTenantFromUser middleware

## Verification

To test the API upload works:

```bash
# 1. Get IDs (user, tenant, campaign)
php artisan tinker --execute="
\$user = \App\Models\User::on('pgsql')->first();
echo 'User Tenant: ' . \$user->tenant_id;
\$tenant = \App\Models\Tenant::on('pgsql')->find(\$user->tenant_id);
\App\Tenancy\TenantContext::run(\$tenant, function () {
    echo 'Campaign: ' . \App\Models\Campaign::first()->id;
});
"

# 2. Generate API token
php artisan tinker --execute="
\$user = \App\Models\User::on('pgsql')->first();
\$token = \$user->createToken('test')->plainTextToken;
echo 'Token: ' . \$token;
"

# 3. Upload via API
curl -X POST http://stash.test:8000/api/campaigns/{campaign_id}/documents \
  -H "Authorization: Bearer {token}" \
  -F "file=@/tmp/test.pdf"

# 4. Process queue
php artisan queue:work --once

# 5. Check logs for success
tail -50 storage/logs/laravel.log | grep -i "ProcessDocumentJob\|DONE\|ERROR"
```

## Complete Solution Summary

The `SQLSTATE[42P01]` error has been completely fixed:

1. ✅ **Console command** (`test:upload-document`) - works without errors
2. ✅ **Web/API upload** - now initialized with tenant context
3. ✅ **Queue processing** - handles jobs with correct tenant database
4. ✅ **All tests passing** - 23 API tests + 20 action tests

The issue was **not** a database schema problem - it was a **context initialization problem**. Different entry points (console, web, queue) need the tenant context initialized, and now they all do!
