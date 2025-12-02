# Quick Start Guide for AI Sessions

## 🚀 Project At a Glance

**Status**: Production-ready Laravel 12 + Inertia v2 + Vue 3 application
**Core Feature**: Multi-tenant document processing with Laravel Workflow

## Critical Knowledge for New Sessions

### 1. Workflow System (MOST IMPORTANT)

**Current Architecture** (Phase 5-6 Complete):
```
Document Upload
  ↓
DocumentProcessingPipeline::process()
  ↓
DocumentProcessingWorkflow (Laravel Workflow)
  ├── OcrActivity
  ├── ClassificationActivity
  ├── ExtractionActivity
  └── ValidationActivity
```

**What Was Removed**:
- ❌ `ProcessDocumentJob` (legacy queue job)
- ❌ `SetTenantContext` middleware
- ❌ Manual pipeline orchestration

**Key Files**:
- `app/Workflows/DocumentProcessingWorkflow.php`
- `app/Workflows/Activities/*.php`
- `app/Listeners/WorkflowCompletedListener.php`

### 2. State Management (CRITICAL)

**NEVER use 'status' strings - ALWAYS use state classes**:

```php
// ❌ WRONG - Don't do this
Campaign::create(['status' => 'active']);
Document::create(['status' => 'completed']);

// ✅ CORRECT - Always use state classes
use App\States\Campaign\ActiveCampaignState;
use App\States\Document\CompletedDocumentState;

Campaign::create(['state' => ActiveCampaignState::class]);
Document::create(['state' => CompletedDocumentState::class]);
```

**Available States**:
- Campaign: `ActiveCampaignState`, `DraftCampaignState`, `PausedCampaignState`, `ArchivedCampaignState`
- Document: `CompletedDocumentState`, `ProcessingDocumentState`, `FailedDocumentState`, `PendingDocumentState`

### 3. Multi-Tenancy Pattern (ESSENTIAL)

**Database Structure**:
- **Central DB**: `tenants`, `users`
- **Tenant DBs**: `campaigns`, `documents`, `document_jobs`

**Pattern for Tenant Operations**:
```php
use App\Tenancy\TenantContext;

// Always wrap tenant operations
TenantContext::run($tenant, function () {
    $campaign = Campaign::factory()->create();
    // ... tenant-scoped operations
});
```

**Test Pattern**:
```php
use App\Tenancy\TenantContext;
use Tests\Support\UsesDashboardSetup;

uses(UsesDashboardSetup::class);

test('example', function () {
    [$tenant, $user] = $this->setupDashboardTestTenant();
    
    TenantContext::run($tenant, function () {
        // Test logic with tenant context
    });
});
```

### 4. Test Suite Status

**Current**: 364/428 passing (85%)
- ✅ Core workflow: 16/16 (100%)
- ✅ Document upload: 19/19 (100%)
- ⚠️ Remaining 64 failures: Peripheral features

**When Writing Tests**:
- Use `tests/Feature/DeadDrop/` for tenant-related tests
- Extend `DeadDropTestCase` for base class
- Use `UsesDashboardSetup` trait
- Wrap logic in `TenantContext::run()`

**See**: `TEST_FIX_SUMMARY.md` for detailed status

### 5. Monitoring & Observability

**Available Dashboards**:
- `/waterline` - Workflow execution monitoring
- `/horizon` - Queue job monitoring

**Queue Requirements**:
- Workflows REQUIRE queue workers (NOT sync)
- Development: `composer run dev` (includes Horizon)
- Production: `php artisan horizon`

## Common Tasks Quick Reference

### Run Tests
```bash
php artisan test                           # All tests
php artisan test --filter WorkflowTest    # Specific test
php artisan test tests/Feature/DeadDrop/  # Tenant tests
```

### Start Development
```bash
composer run dev  # Starts server + horizon + logs + vite
# Access: http://stash.test:8000
```

### Create Models/Migrations
```php
php artisan make:model MyModel --factory --migration

// IMPORTANT: If tenant-scoped, set connection
class MyModel extends Model {
    protected $connection = 'tenant';
}
```

### Working with States
```php
// Transition state
$campaign->state->transitionTo(ActiveCampaignState::class);
$campaign->save();

// Check state
if ($campaign->state instanceof ActiveCampaignState) {
    // ...
}

// Query by state
Campaign::whereState('state', ActiveCampaignState::class)->get();
```

## Critical "Don'ts"

1. ❌ **NEVER** use `'status' => 'active'` - use state classes
2. ❌ **NEVER** create tenant models without `TenantContext::run()`
3. ❌ **NEVER** reference `ProcessDocumentJob` - it was removed
4. ❌ **NEVER** use `QUEUE_CONNECTION=sync` with workflows
5. ❌ **NEVER** assume User model uses 'tenant' connection - it's 'central'

## When Things Break

### "SQLSTATE[23502]: null value in column 'tenant_id'"
→ Missing tenant context. Use `TenantContext::run($tenant, fn() => ...)`

### "Undefined column: status"
→ Using 'status' instead of state classes. See State Management section above.

### "Class ProcessDocumentJob not found"
→ Legacy code reference. Use `DocumentProcessingWorkflow` instead.

### "Database does not exist"
→ Tests need `UsesDashboardSetup::setupDashboardTestTenant()`

### Tests failing after changes
→ Run full test suite: `php artisan test` and check for regressions

## Key Files to Read First

**For Understanding Architecture**:
1. `WARP.md` - Project conventions (read "Project Status" section first)
2. `DEPLOYMENT_READY.md` - Current system status
3. `LARAVEL_WORKFLOW_ARCHITECTURE.md` - Workflow details

**For Fixing Tests**:
1. `TEST_FIX_SUMMARY.md` - Test status and patterns
2. `.ai/guidelines/stash/tdd-tenancy-workflow.md` - TDD workflow

**For Implementing Features**:
1. `.ai/guidelines/stash/domain.md` - Domain architecture
2. `.ai/guidelines/stash/testing.md` - Testing patterns

## Quick Wins Checklist

Before making changes, verify:
- [ ] Read WARP.md "Project Status" section
- [ ] Understand we use Laravel Workflow (not ProcessDocumentJob)
- [ ] Know to use state classes (not 'status' strings)
- [ ] Know tenant operations need TenantContext::run()
- [ ] Checked TEST_FIX_SUMMARY.md for test patterns

## Need More Details?

- **Workflow specifics**: See `LARAVEL_WORKFLOW_ARCHITECTURE.md`
- **Deployment**: See `DEPLOYMENT_READY.md`
- **Testing patterns**: See `TEST_FIX_SUMMARY.md`
- **Multi-tenancy**: See `.ai/guidelines/stash/tdd-tenancy-workflow.md`
- **Full architecture**: See `.ai/guidelines/stash/domain.md`

---

**Last Updated**: December 2024 - Phase 5-6 Complete
**Test Status**: 364/428 passing (85% - core 100%)
**Production Status**: ✅ READY
