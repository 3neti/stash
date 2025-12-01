# ProcessDocumentJob Error Investigation & Fix Summary

## Problem
When uploading a document and ProcessDocumentJob executed, the application threw:
```
SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "channels" does not exist
```

Additionally, when the job failed and tried to mark itself as failed again, it threw:
```
Transition from `failed` to `failed` on model `App\Models\DocumentJob` was not found
```

## Root Causes

### 1. Missing Channels Table in Tenant Database
- The `Campaign` model uses the `HasChannels` trait from the `model-channel` package
- When `ProcessDocumentJob` executed in a tenant context and queried the Campaign model, it tried to access channels
- The `channels` table only existed in the central database (from model-channel's service provider migrations)
- The tenant database had no `channels` table, causing the query to fail

### 2. Missing State Transition for Failed → Failed
- The `DocumentJobState` configuration didn't allow a transition from `failed` state to `failed` state
- When `ProcessDocumentJob::handleFailure()` called `$documentJob->fail()` on a job already in `failed` state, the state machine threw an error
- This happened when retries were exhausted and the job tried to mark itself as failed

## Solutions Implemented

### 1. Created Tenant-Specific Channels Migration
**File**: `database/migrations/tenant/2025_12_01_080000_create_channels_table.php`

Created a migration that creates the `channels` table in the tenant database with:
- `id` (bigserial primary key)
- `name` (varchar)
- `value` (varchar)  
- `model_type` (varchar) - for polymorphic relationships
- `model_id` (ULID) - for campaign IDs
- `created_at`, `updated_at` timestamps
- Indexes on `(model_type, model_id)` and `name`

The migration uses `Schema::hasTable()` check to prevent duplicate creation errors.

### 2. Disabled Model-Channel Service Provider Migrations
**File**: `packages/model-channel/src/ModelChannelServiceProvider.php`

Commented out the line that loads migrations from the model-channel package:
```php
// Migrations are handled separately in the main application
// $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
```

This prevents the central database migration from being auto-loaded, allowing us to manage migrations separately for each database context (central vs. tenant).

### 3. Added Failed → Failed State Transition
**File**: `app/States/DocumentJob/DocumentJobState.php`

Added the state transition:
```php
->allowTransition(FailedJobState::class, FailedJobState::class) // Allow re-failing
```

This permits a job that's already in a failed state to be marked as failed again without throwing a state machine error.

## Testing

Created comprehensive tests in `tests/Feature/DeadDrop/ProcessDocumentJobFixTest.php`:

1. **test_process_document_job_runs_without_channels_table_error()**
   - Verifies ProcessDocumentJob can execute without channels table errors
   - ✅ PASSED

2. **test_document_job_can_transition_from_failed_to_failed()**
   - Verifies a job can transition from failed state to failed state
   - Tests that `fail()` can be called multiple times
   - ✅ PASSED

3. **test_campaign_with_channels_trait_loads_without_error()**
   - Verifies Campaign model with HasChannels trait loads correctly
   - Tests that `$campaign->webhook` accessor works without errors
   - ✅ PASSED

All tests pass successfully.

## Migration Steps for Production

To apply these fixes:

1. **Deploy code changes**:
   - Updated `DocumentJobState` with new state transition
   - New tenant migration file
   - Updated model-channel ServiceProvider

2. **Run migrations**:
   ```bash
   php artisan migrate                    # Central database
   php artisan tenant:migrate             # Tenant databases
   ```

3. **Test document upload**:
   - Upload a document to a campaign
   - Process should now complete without channels table errors

## Files Modified

- `app/States/DocumentJob/DocumentJobState.php` - Added failed → failed transition
- `packages/model-channel/src/ModelChannelServiceProvider.php` - Disabled package migrations
- `database/migrations/tenant/2025_12_01_080000_create_channels_table.php` - New tenant migration
- `tests/Feature/DeadDrop/ProcessDocumentJobFixTest.php` - New integration tests

## Impact

- ✅ ProcessDocumentJob can now execute without database errors
- ✅ Failed jobs can be marked as failed multiple times without state errors
- ✅ Campaign model relationships work correctly in tenant context
- ✅ Channels table properly exists in tenant databases
- ✅ Model-channel package is properly integrated without migration conflicts

## Verification

Run the following to verify all fixes are working:

```bash
# Run the new tests
php artisan test tests/Feature/DeadDrop/ProcessDocumentJobFixTest.php

# Test a document upload manually
# 1. Go to http://stash.test/campaigns/[id]
# 2. Upload a document (e.g., ~/Downloads/DigitalOcean Invoice)
# 3. Verify no database errors in logs
# 4. Check that document status updates correctly
```
