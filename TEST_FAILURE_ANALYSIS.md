# Test Failure Analysis - December 4, 2024

## Executive Summary

**Total Tests**: 510 (420 passing, 84 failing, 6 skipped)  
**Pass Rate**: 82.4%  
**Critical Fix Applied**: Laravel 12 compatibility for `DeadDropTestCase`

---

## Failure Breakdown by Error Type

### 1. UniqueConstraintViolationException (20 failures, 24%)

**Root Cause**: Tests creating tenants/documents with duplicate slugs or IDs

#### 1a. Tenant Slug Conflicts (16 failures)
**Files Affected**:
- `Tests\Unit\Models\ContactMediaTest` (4 failures)
- `Tests\Feature\Workflows\FeatureFlagIntegrationTest` (7 failures)
- `Tests\Feature\Workflows\AdvancedDocumentProcessingWorkflowTest` (4 failures)
- `Tests\Feature\TenancyTest` (4 failures)
- `Tests\Feature\DeadDrop\DocumentUploadRouteTest` (1 failure)

**Error Pattern**:
```
SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "tenants_slug_unique"
Key (slug)=(test-tenant) already exists.
```

**Root Cause**: Tests using hardcoded tenant slug `"test-tenant"` instead of unique slugs

**Solution**: Use `UsesDashboardSetup` trait which generates unique slugs via `uniqid()`
```php
// ❌ Wrong
$tenant = Tenant::factory()->create(['slug' => 'test-tenant']);

// ✅ Correct
[$tenant, $user] = $this->setupDashboardTestTenant();
// Generates: 'test-company-675068d2b1234'
```

#### 1b. Document ID Conflicts (4 failures)
**Files Affected**:
- `Tests\Feature\DeadDrop\DocumentUploadRouteTest` (1 failure)

**Error Pattern**:
```
SQLSTATE[23505]: duplicate key value violates unique constraint "documents_pkey"
```

**Root Cause**: Document factories generating duplicate ULIDs across parallel tests

---

### 2. QueryException (21 failures, 25%)

**Root Cause**: Database constraint violations and tenant context issues

#### 2a. Check Constraint Violations (4 failures)
**Files Affected**:
- `Tests\Feature\DeadDrop\CsvImportLocalizedValidationTest` (4 failures)

**Error Pattern**:
```
SQLSTATE[23514]: Check violation: new row for relation "tenants" violates check constraint "tenants_tier_check"
```

**Root Cause**: Tenant factory using invalid `tier` value

**Solution**: Check `database/migrations/central/*_create_tenants_table.php` for valid tier values

#### 2b. Foreign Key Violations (6 failures)
**Files Affected**:
- `Tests\Feature\DeadDrop\CsvImportWithRegexTest` (6 failures)

**Error Pattern**:
```
SQLSTATE[23503]: Foreign key violation: insert or update on table "documents" violates foreign key constraint "documents_campaign_id_foreign"
```

**Root Cause**: Creating documents without valid campaign in tenant context

**Solution**: Wrap campaign/document creation in `TenantContext::run()`

#### 2c. Not Null Violations (7 failures)
**Files Affected**:
- `Tests\Feature\DeadDrop\PipelineEndToEndTest` (4 failures)
- `Tests\Feature\Phase12IntegrationTest` (1 failure)
- `Tests\Feature\DeadDrop\ProductionWorkflowTest` (1 failure)
- `Tests\Feature\DeadDrop\ProductionInitializationTest` (1 failure)

**Error Pattern**:
```
SQLSTATE[23502]: Not null violation: null value in column "tenant_id" of relation "document_jobs" violates not-null constraint
```

**Root Cause**: Models missing `tenant_id` when created outside tenant context

**Solution**: Ensure all tenant-scoped models created within `TenantContext::run()`

#### 2d. Other Query Exceptions (4 failures)
- `Tests\Feature\DeadDrop\PipelineEndToEndTest` - Connection issues

---

### 3. TypeError (9 failures, 11%)

**Files Affected**:
- `Tests\Feature\DeadDrop\ProcessHypervergeWebhookTest` (9 failures)

**Error Pattern**:
```
Spatie\WebhookClient\Jobs\ProcessWebhookJob::__construct(): Argument #1 ($webhookCall) 
must be of type Spatie\WebhookClient\Models\WebhookCall, array given
```

**Root Cause**: Test passing raw array to webhook job instead of WebhookCall model instance

**Current Code** (line 74, 93, 105, 173):
```php
// ❌ Wrong
ProcessWebhookJob::dispatch([
    'payload' => $payload,
]);
```

**Solution**: Create WebhookCall model first
```php
// ✅ Correct
$webhookCall = WebhookCall::create([
    'name' => 'hyperverge',
    'url' => 'https://...',
    'payload' => $payload,
]);
ProcessWebhookJob::dispatch($webhookCall);
```

---

### 4. BindingResolutionException (5 failures, 6%)

**Files Affected**:
- `Tests\Unit\Services\HyperVerge\KycDataMapperTest` (5 failures)

**Error Pattern**:
```
Target class [App\Services\HyperVerge\KycDataMapper] does not exist.
```

**Root Cause**: Missing service binding or class not found

**Solution**: Either:
1. Bind service in `AppServiceProvider`
2. Fix namespace/file location
3. Check if class exists in codebase

---

### 5. ErrorException (5 failures, 6%)

**Files Affected**:
- `Tests\Feature\Console\CleanupOldKycMediaTest` (4 failures)
- `Tests\Feature\DeadDrop\EKycVerificationProcessorTest` (1 failure)
- `Tests\Feature\DeadDrop\CsvImportWithRegexTest` (1 failure)

**Error Patterns**: Various (need individual inspection)

---

### 6. Assertion Failures (20 failures, 24%)

**Categories**:

#### 6a. CampaignAccessWithoutSQLSTATETest (3 failures)
- Tests expecting successful page loads but getting errors

#### 6b. EKycVerificationProcessorTest (2 failures)
- Processor behavior not matching expectations

#### 6c. Browser Test Failures (1 failure)
**File**: `Tests\Browser\Campaigns\CampaignTest`
```
Saw unexpected text [SQLSTATE] within element [body]
```
**Root Cause**: Campaign detail page still showing database errors in browser context

#### 6d. Other Assertions (14 failures)
- Various feature/integration tests with logic issues

---

### 7. Other Exceptions (4 failures, 5%)

- **ModelNotFoundException** (1): `DocumentProcessingWorkflowTest`
- **PDOException** (1): `TenantSchemaGuardTest`
- **Error** (1): `CleanupOldKycMediaTest`
- Various assertion failures (1)

---

## Priority Fix Recommendations

### 🔴 Critical (Fix First)

**1. Unique Tenant Slugs (16 failures)**
- **Impact**: Blocking 19% of failures
- **Effort**: Low (find/replace pattern)
- **Files**: ContactMediaTest, FeatureFlagIntegrationTest, AdvancedWorkflowTest, TenancyTest
- **Fix**: Replace hardcoded slugs with `setupDashboardTestTenant()`

**2. Webhook TypeError (9 failures)**
- **Impact**: 11% of failures
- **Effort**: Low (single test file)
- **File**: ProcessHypervergeWebhookTest
- **Fix**: Create WebhookCall models instead of passing arrays

### 🟡 High Priority

**3. Tenant Context Issues (13 failures)**
- **Impact**: Foreign key + not null violations
- **Effort**: Medium (multiple files)
- **Files**: CsvImportWithRegexTest, PipelineEndToEndTest
- **Fix**: Wrap tenant-scoped operations in `TenantContext::run()`

**4. Tier Constraint (4 failures)**
- **Impact**: 5% of failures
- **Effort**: Low (check migration)
- **File**: CsvImportLocalizedValidationTest
- **Fix**: Use valid tier values from database enum

### 🟢 Medium Priority

**5. BindingResolutionException (5 failures)**
- **Effort**: Medium (investigate missing class)
- **File**: KycDataMapperTest

**6. Browser Test (1 failure)**
- **Effort**: High (depends on other fixes)
- **File**: CampaignTest

### 🔵 Low Priority

**7. Assertion Failures (20 failures)**
- **Effort**: High (case-by-case analysis)
- **Strategy**: Fix after resolving database/context issues

---

## Fix Pattern Reference

### Pattern 1: Use Unique Tenant Slugs
```php
use Tests\Support\UsesDashboardSetup;

uses(UsesDashboardSetup::class);

beforeEach(function () {
    [$tenant, $user] = $this->setupDashboardTestTenant();
    $this->tenant = $tenant;
    $this->user = $user;
});
```

### Pattern 2: Tenant Context Wrapping
```php
use App\Tenancy\TenantContext;

test('example', function () {
    TenantContext::run($this->tenant, function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->for($campaign)->create();
        // All tenant-scoped operations here
    });
});
```

### Pattern 3: WebhookCall Creation
```php
use Spatie\WebhookClient\Models\WebhookCall;

$webhookCall = WebhookCall::create([
    'name' => 'hyperverge',
    'url' => config('services.hyperverge.webhook_url'),
    'payload' => $payload,
    'headers' => [],
]);

ProcessWebhookJob::dispatch($webhookCall);
```

---

## Estimated Fix Effort

| Priority | Failures | Estimated Time |
|----------|----------|----------------|
| Critical | 25 (30%) | 2-3 hours |
| High     | 17 (20%) | 3-4 hours |
| Medium   | 6 (7%)   | 2-3 hours |
| Low      | 36 (43%) | 8-10 hours |
| **Total** | **84** | **15-20 hours** |

---

## Next Steps

1. **Start with Critical Fixes** (25 failures):
   - Replace hardcoded tenant slugs (16 tests)
   - Fix webhook TypeError (9 tests)

2. **Move to High Priority** (17 failures):
   - Add tenant context wrapping (13 tests)
   - Fix tier constraint (4 tests)

3. **Address Medium Priority** (6 failures):
   - Investigate KycDataMapper binding (5 tests)
   - Fix browser test (1 test)

4. **Low Priority** (36 failures):
   - Case-by-case analysis after database issues resolved

---

## Files Requiring Attention

### High Impact (10+ failures each)
- ✅ **DeadDropTestCase.php** - Fixed (Laravel 12 compatibility)

### Medium Impact (5-9 failures each)
- `tests/Feature/DeadDrop/ProcessHypervergeWebhookTest.php` (9)
- `tests/Feature/Workflows/FeatureFlagIntegrationTest.php` (7)
- `tests/Feature/DeadDrop/CsvImportWithRegexTest.php` (7)

### Low Impact (1-4 failures each)
- Multiple files with 1-4 failures each (see categories above)

---

## Success Metrics

**Target**: 95%+ pass rate (485+ passing tests)

- Phase 1 (Critical): 420 → 445 passing (+25)
- Phase 2 (High): 445 → 462 passing (+17)
- Phase 3 (Medium): 462 → 468 passing (+6)
- Phase 4 (Low): 468 → 504 passing (+36)

**Current**: 420/510 = 82.4%  
**Target**: 504/510 = 98.8%
