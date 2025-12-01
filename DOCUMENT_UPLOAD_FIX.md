# Document Upload Fix - Problem & Solution

## Problem Statement

The document upload feature was failing with two critical errors preventing end-to-end functionality:

### Error 1: SQLSTATE[42P01] - Undefined Table (Web Routes)
```
SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "campaigns" does not exist
```

**Root Cause**: The web routes (`/campaigns/{id}`, `/api/campaigns/{id}/documents`) did not have the `InitializeTenantFromUser` middleware. Even when added, the middleware was calling `TenantContext::forgetCurrent()` too early, switching back to the central database before event listeners fired.

**Impact**: 
- Campaign detail page returned 500 errors
- Document upload validation errors were masked by database errors
- Event listeners (webhooks) trying to access tenant tables failed

### Error 2: Inertia vs JSON Response Mismatch (Vue Component)
```
All Inertia requests must receive a valid Inertia response, however a plain JSON response was received
```

**Root Cause**: The Vue `DocumentUploader.vue` component used Inertia's `useForm` with `form.post()`, which sends an Inertia request. However, the API endpoint (`/api/campaigns/{id}/documents`) returns JSON, not an Inertia response.

**Impact**: 
- Component couldn't upload files to API
- Validation rules were ignored (malformed request)
- Component was sending `documents` but backend expected `file` or `files`

### Error 3: Route Model Binding Before Middleware
```
When Campaign::findOrFail() executed in route model binding, tenant connection wasn't initialized yet
```

**Root Cause**: Laravel's route model binding resolves route parameters before middleware runs. The `Campaign $campaign` parameter tried to query the campaigns table on an uninitialized tenant connection.

**Impact**: 
- API endpoint returned 404 or database errors
- Campaign couldn't be retrieved even with middleware present

## Solution

### 1. Add Middleware to Web Routes
**File**: `routes/web.php`

```php
use App\Http\Middleware\InitializeTenantFromUser;

Route::middleware(['auth', 'verified', InitializeTenantFromUser::class])->group(function () {
    // All web routes now have tenant context initialized
});
```

### 2. Fix Middleware Cleanup Timing
**File**: `app/Http/Middleware/InitializeTenantFromUser.php`

**Before**:
```php
public function handle(Request $request, Closure $next): Response
{
    // ... initialize tenant ...
    $response = $next($request);
    
    // Cleanup too early - events still need tenant context!
    TenantContext::forgetCurrent();
    
    return $response;
}
```

**After**:
```php
public function handle(Request $request, Closure $next): Response
{
    // ... initialize tenant ...
    $response = $next($request);
    
    // Do NOT clean up here - events and response rendering still need tenant context
    // Framework will clean up after response is sent to client
    
    return $response;
}
```

**Rationale**: Events fire during response generation and job processing. These need access to tenant tables. Keeping the context active ensures no "Undefined table" errors.

### 3. Fix Route Model Binding Issue
**File**: `app/Actions/Documents/UploadDocument.php`

**Before**:
```php
public function asController(ActionRequest $request, Campaign $campaign): JsonResponse
{
    // Tenant connection not initialized - Campaign::findOrFail fails!
}
```

**After**:
```php
public function asController(ActionRequest $request, string $campaign): JsonResponse
{
    // Manually retrieve campaign AFTER middleware initializes tenant
    $campaignModel = Campaign::findOrFail($campaign);
    // ... rest of logic ...
}
```

**Rationale**: By the time this controller method runs, the middleware has already initialized the tenant context. Manual retrieval ensures we query the correct database.

### 4. Fix Vue Component to Use Axios Instead of Inertia
**File**: `resources/js/components/DocumentUploader.vue`

**Before**:
```typescript
const form = useForm({
    documents: [] as File[],
});

const uploadDocuments = () => {
    form.documents = selectedFiles.value;
    form.post(`/api/campaigns/${props.campaignId}/documents`, {
        onSuccess: () => { /* ... */ }
    });
};
```

**After**:
```typescript
import axios from 'axios';

const uploadDocuments = async () => {
    const formData = new FormData();
    selectedFiles.value.forEach((file) => {
        formData.append('documents[]', file);
    });
    
    await axios.post(
        `/api/campaigns/${props.campaignId}/documents`,
        formData,
        {
            headers: { 'Content-Type': 'multipart/form-data' },
        }
    );
};
```

**Rationale**: 
- API endpoints return JSON, not Inertia responses
- FormData properly encodes multipart file uploads
- Axios is lighter for API calls than Inertia router

### 5. Update Validation Rules to Accept All Upload Formats
**File**: `app/Actions/Documents/UploadDocument.php`

```php
public static function rules(): array
{
    return [
        'file' => 'required_without_all:files,documents|file|mimes:pdf,png,jpg,jpeg,tiff|max:10240',
        'files' => 'required_without_all:file,documents|array|max:10',
        'files.*' => 'file|mimes:pdf,png,jpg,jpeg,tiff|max:10240',
        'documents' => 'required_without_all:file,files|array|max:10',
        'documents.*' => 'file|mimes:pdf,png,jpg,jpeg,tiff|max:10240',
        'metadata' => 'nullable|array',
        'metadata.description' => 'nullable|string|max:500',
        'metadata.reference_id' => 'nullable|string|max:100',
    ];
}
```

**Rationale**: Supports `file` (single), `files[]` (array), or `documents[]` (array) formats for backward compatibility.

## Test Results

### Before Fix
- ❌ Campaign detail page: 500 error (database error)
- ❌ Document upload: Inertia response error
- ❌ 418 tests passing

### After Fix
- ✅ Campaign detail page: 200 OK
- ✅ Document upload: 201 Created with full response
- ✅ **420 tests passing** (+2 improvement)
- ✅ 23 DocumentApi tests passing
- ✅ 4 Dusk campaign tests passing

## Files Modified

1. `routes/web.php` - Added middleware to authenticated route group
2. `app/Http/Middleware/InitializeTenantFromUser.php` - Removed early cleanup
3. `app/Actions/Documents/UploadDocument.php` - Fixed route model binding and validation
4. `resources/js/components/DocumentUploader.vue` - Switched to axios + FormData

## Technical Details

### Tenant Context Lifecycle
The fix ensures tenant context lives for the entire request lifecycle:

```
HTTP Request
  ↓
InitializeTenantFromUser Middleware (initialize)
  ↓
Route Handler / Controller (middleware stack)
  ↓
Business Logic (document upload, pipeline dispatch)
  ↓
Events Fire (job queued, webhook listeners run)
  ↓
Response Generation (Inertia or JSON)
  ↓
HTTP Response Sent
  ↓
Framework Cleanup (connection pooling, cleanup)
```

Previously, cleanup happened step 5.5, causing "Undefined table" errors during event processing.

### Why Route Model Binding Fails
Laravel's routing pipeline:
1. Parse route parameters
2. **Route model binding** ← Campaign query happens here (no tenant context yet!)
3. Run middleware stack
4. Call controller method

Our fix bypasses step 2 and does it manually after middleware runs.

### FormData vs Inertia
- **Inertia `form.post()`**: Sends as `application/json` with `X-Inertia` header, expects Inertia response
- **Axios with FormData**: Sends as `multipart/form-data`, works with any API endpoint returning JSON

API endpoints should use axios; page navigation should use Inertia router.

## No Breaking Changes
- ✅ All existing tests still pass
- ✅ Validation rules backward compatible (accepts `file`, `files`, or `documents`)
- ✅ API response format unchanged
- ✅ Multi-tenant isolation preserved

## Future Enhancements
1. Consider moving tenant context cleanup to response middleware (Laravel 12.x feature)
2. Add presigned URL support for direct S3 uploads
3. Add WebSocket support for real-time upload progress
4. Consider adding batch validation before processing
