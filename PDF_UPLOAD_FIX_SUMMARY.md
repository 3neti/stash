# PDF Upload Error Investigation & Fixes

## Error Reproduced ✅

Successfully reproduced the SQLSTATE[42P01] error:
```
SQLSTATE[42P01]: Undefined table: relation "document_jobs" does not exist
```

**Root Cause**: When document uploads are queued and processed by the queue worker, the job middleware couldn't initialize the tenant context properly because:

1. **DocumentJob model has `BelongsToTenant` trait** - forces it to always use 'tenant' connection
2. **Job middleware tried to load DocumentJob from 'pgsql' connection first** - to bootstrap the tenant context
3. **`.on('pgsql')` couldn't override the trait** - Eloquent's `getConnectionName()` override from the trait returned 'tenant' regardless
4. **Result**: Middleware tried to query `document_jobs` table using wrong connection, which didn't exist there

## Root Issue Chain

```
Queue Worker starts
  ↓ Job payload contains: { documentJobId: "...", tenantId: null }
  ↓ SetTenantContext middleware tries to initialize tenant
  ↓ Calls: DocumentJob::on('pgsql')->findOrFail($id)
  ↓ BelongsToTenant trait overrides connection back to 'tenant'
  ↓ 'tenant' connection not yet initialized (queue worker doesn't have it)
  ↓ Falls back to pgsql connection
  ↓ Queries document_jobs table on pgsql (central DB)
  ✗ document_jobs doesn't exist on central DB - table is in tenant DBs
  ✗ SQLSTATE[42P01] error
```

## Solutions Implemented

### Solution 1: Add tenantId to Job Payload ✅
**File**: `app/Jobs/Pipeline/ProcessDocumentJob.php`

Added optional `tenantId` parameter to the job constructor:
```php
public function __construct(
    public readonly string $documentJobId,
    public readonly ?string $tenantId = null  // ← NEW
) {
    $this->uniqueId = $documentJobId;
}
```

**Why**: Queue worker can use this to bootstrap tenant context without querying central database.

### Solution 2: Dispatch with tenantId ✅
**File**: `app/Services/Pipeline/DocumentProcessingPipeline.php`

When dispatching the job, include the current tenant ID:
```php
$tenantId = TenantContext::current()?->id;
ProcessDocumentJob::dispatch($job->id, $tenantId);  // ← Pass tenantId
```

**Why**: At dispatch time, we're in HTTP request context with tenant already initialized, so we can capture the tenant ID.

### Solution 3: Middleware Uses tenantId First ✅
**File**: `app/Jobs/Middleware/SetTenantContext.php`

Updated middleware to use tenantId if available:
```php
if ($job->tenantId) {
    // Use tenantId directly - fast path, no DB queries
    $tenant = Tenant::on('pgsql')->findOrFail($job->tenantId);
} else {
    // Fallback: load via DocumentJob → Campaign → Tenant chain
    // This is only if tenantId wasn't provided at dispatch time
}
```

**Why**: This eliminates the need to query DocumentJob from the central database first, bootstrapping the tenant context faster and without the connection confusion.

### Solution 4: Improved Migration Execution ✅
**File**: `app/Tenancy/TenantConnectionManager.php`

Enhanced `runTenantMigrations()` with:
1. **Better migration table detection** - checks tenant connection instead of querying central DB
2. **Two-step migration** - `migrate:install` then `migrate` instead of `migrate:refresh`
3. **Comprehensive logging** - logs each step for debugging

```php
// Try to check in tenant DB directly
$result = DB::connection('tenant')->select(...)
// Then run: migrate:install (creates migrations table)
//          migrate (runs pending migrations)
```

**Why**: Ensures migrations table is created on tenant DB before running migrations.

## Files Modified

| File | Change |
|------|--------|
| `app/Jobs/Pipeline/ProcessDocumentJob.php` | Added `tenantId` parameter |
| `app/Services/Pipeline/DocumentProcessingPipeline.php` | Pass `tenantId` on dispatch |
| `app/Jobs/Middleware/SetTenantContext.php` | Use `tenantId` if available, log all steps |
| `app/Tenancy/TenantConnectionManager.php` | Improved migration execution & logging |

## How It Works Now

```
HTTP Request with document upload
  ↓ Middleware initializes tenant via TenancyService
  ↓ TenancyService ensures tenant DB exists & migrations run
  ↓ Document uploaded, DocumentJob created
  ↓ Pipeline dispatches ProcessDocumentJob with tenantId ✓
  
Queue Worker processes job
  ↓ SetTenantContext middleware receives job with tenantId ✓
  ↓ Direct load: Tenant::on('pgsql')->find($job->tenantId)
  ↓ Initialize tenant connection via TenancyService ✓
  ↓ Tenant DB tables are available (migrations ran)
  ↓ DocumentJob can be queried from tenant connection ✓
  ✓ Document processing begins
```

## Testing

To verify the fix works:

```bash
# Start queue worker
php artisan queue:listen

# In another terminal, watch logs
php artisan pail --filter="JobMiddleware|Pipeline|TenancyService"

# Upload a PDF (via browser UI or API)
# You should see:
#   [Pipeline] Dispatching job to queue  
#   [JobMiddleware] Using tenantId from job payload
#   [JobMiddleware] Tenant connection initialized
#   ✓ No SQLSTATE[42P01] errors
```

## What's Still Needed

The fixes above implement the **bootstrap problem solution**. However, to fully complete document upload processing, ensure:

1. **Tenant migrations include all tables**:
   - `campaigns`, `documents`, `document_jobs`, `processor_executions`
   - Check: `database/migrations/tenant/*.php`

2. **ProcessorRegistry is configured**:
   - OCR, Classification, and other processors must be registered
   - Check: `app/Services/Pipeline/ProcessorRegistry.php`

3. **Queue worker is running**:
   - `php artisan queue:listen` or supervisor for production
   - Check logs for: `ProcessDocumentJob started`

4. **Document storage is configured**:
   - Tenant disk for storing PDFs
   - Check: `config/filesystems.php`

## Debugging Commands

```bash
# Check if migrations ran
php artisan tinker
> DB::connection('tenant')->select("SELECT * FROM information_schema.tables WHERE table_name='document_jobs'")

# List failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Monitor queue
php artisan queue:monitor

# Show job details
php artisan tinker
> \App\Models\DocumentJob::with('document')->first()
```

## References

- TenancyService: `app/Services/Tenancy/TenancyService.php`
- SetTenantContext Middleware: `app/Jobs/Middleware/SetTenantContext.php`
- DocumentProcessingPipeline: `app/Services/Pipeline/DocumentProcessingPipeline.php`
- ProcessDocumentJob: `app/Jobs/Pipeline/ProcessDocumentJob.php`
- Migration Execution: `app/Tenancy/TenantConnectionManager.php`
