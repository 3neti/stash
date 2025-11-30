# TDD Workflow for Multi-Tenant Database Issues

## Overview
This document describes the proven TDD workflow for debugging and fixing database connection errors in the multi-tenant Stash application. When a live browser feature fails with database connection errors (e.g., "SQLSTATE[42P01]: Undefined table"), follow this 4-phase workflow instead of debugging randomly.

**Key Principle**: Always start with failing tests. Never modify production code without first having a test that reproduces the bug.

## When to Use This Workflow

Use this workflow when you encounter:
- `SQLSTATE[42P01]: Undefined table: "tablename"`
- `SQLSTATE[08006]: database "tenant_..." does not exist`
- `Connection refused` or `Unknown database` errors
- Any error that occurs when accessing a resource in authenticated routes
- Multi-tenant connection switching issues

**Location**: If the issue is related to multi-tenant connections or the DeadDrop environment, create tests in `tests/Feature/DeadDrop/` directory.

## Phase 1: Write Failing Feature Tests (TDD Red)

### Goal
Create tests that reproduce the bug and fail consistently.

### Implementation Steps

1. **Create test file** in `tests/Feature/DeadDrop/` directory
   - Follow pattern: `{Feature}RouteTest.php` or `{Feature}ConnectionTest.php`
   - Use `DeadDropTestCase` as base class for multi-tenant setup

2. **Set up test structure** with three key parts:
   ```php
   use App\Models\User;
   use App\Models\Tenant;
   use App\Tenancy\TenantContext;
   
   // 1. Create authenticated user with tenant
   $user = User::factory()->create(['email_verified_at' => now()]);
   $tenant = Tenant::factory()->create();
   $user->update(['tenant_id' => $tenant->id]);
   
   // 2. Initialize tenant context
   TenantContext::run($tenant, function () use ($user) {
       // 3. Create resource and test access
       $resource = Resource::factory()->create();
       $response = $this->actingAs($user)->get("/path/{$resource->id}");
       
       // 4. Assert success (the critical part)
       expect($response->status())->toBe(200);
   });
   ```

3. **Run tests to confirm failure**
   ```bash
   php artisan test tests/Feature/DeadDrop/YourRouteTest.php
   ```
   Tests MUST fail with database connection error.

4. **Commit test file**
   ```
   commit: "Phase 1 - Add failing Feature tests for {feature} (TDD)"
   ```

### Why This Step Matters
- Confirms the bug is reproducible in tests
- Provides concrete example of what should work
- Creates safety net for Phase 3 implementation
- Documents expected behavior as code

---

## Phase 2: Debug and Identify Root Cause

### Goal
Understand WHY the test fails instead of applying random fixes.

### Investigation Checklist

**1. Check TenantContext behavior**
   - Is `TenantContext::run()` switching to correct connection?
   - Does it initialize the tenant database properly?
   - Are there transaction issues (PostgreSQL limitation)?
   ```bash
   # Check implementation
   cat app/Tenancy/TenantContext.php
   cat app/Tenancy/TenantConnectionManager.php
   ```

**2. Verify middleware initialization** (if applicable)
   - Is `InitializeTenantFromUser` middleware running?
   - Is it running at correct stage in middleware stack?
   - Is tenant being found and initialized before controller?

**3. Check database configuration**
   - Is 'tenant' connection defined in `config/database.php`?
   - Are all required columns/tables migrated?
   - Does test database exist?

**4. Verify multi-tenant traits**
   - Does model use `BelongsToTenant` trait?
   - Is `getConnectionName()` returning correct connection?
   - Are relationships properly scoped to tenant?

**5. Test isolation considerations**
   - Does `RefreshDatabase` trait interact with multi-tenant setup?
   - Are migrations running on correct connection?
   - Do factories create resources on correct connection?

### Decision Point

After investigation, identify which category the issue falls into:

| Category | Examples | Next Step |
|----------|----------|-----------|
| **Connection not configured** | 'tenant' connection not defined, missing columns | Configure connection or migration |
| **Database doesn't exist** | Individual tenant DB missing in tests | Auto-create databases on-demand |
| **Transaction issue** | "cannot run inside a transaction block" | Commit transaction before DDL statements |
| **Middleware/Context timing** | Middleware not running or tenant not initialized | Fix middleware order or initialization timing |
| **Query scoping** | Model querying wrong table/connection | Fix trait implementation or query scope |

### Documentation
Create brief notes:
```markdown
## Root Cause Analysis for [Feature]

**Symptom**: [Error message]
**Location**: [What fails - model, controller, middleware]
**Root Cause**: [Why it happens]
**Category**: [From decision table above]
**Fix Strategy**: [High-level approach for Phase 3]
```

---

## Phase 3: Implement Fix (TDD Green)

### Goal
Make all failing tests pass without breaking existing tests.

### Critical Safety Checks

Before implementing:
1. **Know which tests might break**: Run full test suite
   ```bash
   php artisan test 2>&1 | tail -5  # Note current count
   ```

2. **Have a rollback plan**: If fix breaks other tests, understand why
   - Don't proceed with breaking changes without evaluation
   - May need to use different approach (Option 1 vs Option 2)

### Implementation Pattern

**Option 1: Minimal change approach** (safest)
- Only modify the failing component
- Add configuration/initialization code
- Avoid changing existing behavior

**Option 2: Auto-provision approach** (for multi-tenant)
- Auto-create resources on-demand in tests
- Recommended when Option 1 would break existing tests
- Ensure provisioning is idempotent (safe to call multiple times)

### Implementation Steps

1. **Modify the component** (e.g., `TenantConnectionManager`)
   - Add auto-creation logic
   - Add safety checks (idempotency)
   - Add clear comments explaining multi-tenant behavior

2. **Handle PostgreSQL specifics** if applicable
   ```php
   // PostgreSQL doesn't allow DDL (CREATE DATABASE) inside transactions
   // Solution: Commit active transaction first
   if ($pdo->inTransaction()) {
       $pdo->commit();
   }
   $pdo->exec('CREATE DATABASE "tenant_id"');
   ```

3. **Run failing tests only first**
   ```bash
   php artisan test tests/Feature/DeadDrop/YourRouteTest.php
   ```
   All should pass now.

4. **Run related tests** to verify no breakage
   ```bash
   php artisan test tests/Feature/TenancyTest.php
   php artisan test tests/Feature/DeadDrop/
   ```

5. **Run full test suite** as final check
   ```bash
   php artisan test  # Should see same or more passing tests
   ```

### Commit Guidelines

```
commit message format:
"Phase 3 - Implement [fix description] (fixes [feature] routes)

- What was changed and why
- How it fixes the specific issue
- Key technical decisions (especially for multi-tenant)
- Test results: X passed, Y skipped, 0 failed
- Confirm no regressions"
```

---

## Phase 4: Verify with Dusk Browser Test (TDD Verify)

### Goal
Confirm fix works end-to-end in actual browser, not just in feature tests.

### Implementation Steps

1. **Locate existing Dusk test** (usually in `tests/Browser/`)
   ```bash
   find tests/Browser -name "*Test.php" | grep -i feature
   ```

2. **Find or create test** for the feature
   - Look for skipped test with explanation
   - Or create new test file in appropriate Browser subdirectory

3. **Update test** to actively verify (remove skip)
   ```php
   test('authenticated user can view campaign detail page', function () {
       $user = User::factory()->create(['email_verified_at' => now()]);
       $tenant = Tenant::factory()->create();
       $user->update(['tenant_id' => $tenant->id]);
       
       $campaign = null;
       TenantContext::run($tenant, function () use (&$campaign) {
           $campaign = Campaign::factory()->create();
       });
       
       $this->browse(function (Browser $browser) use ($user, $campaign) {
           $browser->loginAs($user)
               ->visit("/campaigns/{$campaign->id}")
               ->assertPathIs("/campaigns/{$campaign->id}")
               // Critical: verify no error messages
               ->assertDontSee('SQLSTATE')
               ->assertDontSee('Undefined table');
       });
   });
   ```

4. **Run Dusk tests**
   ```bash
   php artisan dusk tests/Browser/FeatureName/
   ```

5. **Verify full test suite passes**
   ```bash
   php artisan test
   ```

### Why Dusk Test Matters

- Feature tests verify logic in PHP/HTTP context
- Dusk tests verify behavior in actual Chrome browser
- Confirms middleware, session handling, rendering work end-to-end
- Catches issues that don't appear in feature tests

### Commit Guidelines

```
commit message:
"Phase 4 - Verify fix with Dusk browser test

- Enable/update Dusk test for [feature]
- Test verifies [specific behaviors verified]
- Browser successfully [key actions]
- All tests passing: X passed, Y skipped, 0 failed"
```

---

## Complete Workflow Checklist

### Phase 1: Write Failing Tests
- [ ] Create test file in `tests/Feature/DeadDrop/`
- [ ] Use `DeadDropTestCase` as base
- [ ] Write 3+ tests covering different routes/scenarios
- [ ] Tests fail with database connection error
- [ ] Commit Phase 1

### Phase 2: Debug and Identify Root Cause
- [ ] Check `TenantContext` implementation
- [ ] Check middleware initialization
- [ ] Check database configuration
- [ ] Check multi-tenant traits
- [ ] Document root cause analysis
- [ ] Identify fix category (connection, provisioning, transaction, etc.)

### Phase 3: Implement Fix
- [ ] Verify current test count
- [ ] Implement minimal fix
- [ ] Run failing tests → all pass
- [ ] Run related tests → no breakage
- [ ] Run full test suite → same or more passing
- [ ] Commit Phase 3

### Phase 4: Verify with Browser Test
- [ ] Locate/create Dusk test
- [ ] Remove skip or implement new test
- [ ] Run Dusk tests → all pass
- [ ] Run full test suite → all pass
- [ ] Commit Phase 4

---

## Real Example: Campaign Detail Route Fix

This workflow was successfully applied to fix "Undefined table: campaigns" error:

### Phase 1 Result
- Created `tests/Feature/DeadDrop/CampaignDetailRouteTest.php`
- 3 tests: detail page, edit page, delete campaign
- All failed with: `SQLSTATE[42P01]: Undefined table: "campaigns"`

### Phase 2 Result
- Root cause: `TenantContext::run()` tried to switch to tenant databases that didn't exist
- Category: Database doesn't exist / provisioning
- Fix strategy: Auto-create tenant databases on-demand

### Phase 3 Result
- Modified `TenantConnectionManager::switchToTenant()` to check database existence
- Added `createTenantDatabase()` with PostgreSQL transaction workaround
- Added `runTenantMigrations()` to initialize schema
- All 3 campaign tests pass
- All 5 existing tenant tests still pass
- Full suite: 405 passed (no regressions)

### Phase 4 Result
- Enabled Dusk test `authenticated user can view campaign detail page without database error`
- Browser successfully loads campaign detail page
- No SQLSTATE errors appear in response
- Full test suite: 406 passed (including Dusk test)

---

## Best Practices Always Follow

### 1. Run Full Test Suite After Each Phase
```bash
# After Phase 1, 2, 3, and 4
php artisan test
# Note the count - should never decrease (only increase or stay same)
```

### 2. For Multi-Tenant Issues, Use DeadDrop Directory
```bash
# ✅ Correct location for tenant-related tests
tests/Feature/DeadDrop/YourRouteTest.php

# ❌ Don't use generic Feature test directory for tenant issues
tests/Feature/YourRouteTest.php  # Use only for non-tenant routes
```

### 3. Use DeadDropTestCase for Tenant Tests
```php
// ✅ Correct
use Tests\DeadDropTestCase;
class YourTest extends DeadDropTestCase { }

// ❌ Wrong - won't have proper multi-tenant setup
use Tests\TestCase;
class YourTest extends TestCase { }
```

### 4. Always Use TenantContext::run() in Tests
```php
// ✅ Correct - properly initializes tenant context
TenantContext::run($tenant, function () use ($resource) {
    $response = $this->actingAs($user)->get("/path/{$resource->id}");
});

// ❌ Wrong - context not initialized properly
$response = $this->actingAs($user)->get("/path/{$resource->id}");
```

### 5. Test Assertions Should Be Specific
```php
// ✅ Clear and specific
expect($response->status())->toBe(200);
expect($response->status())->toBe(302);  // For redirects

// ❌ Vague - doesn't help identify issues
$this->assertTrue($response->ok());

// ❌ Over-specific - brittle to UI changes
$response->assertSee('Campaign Name');
```

### 6. Verify No Regressions Explicitly
```bash
# After Phase 3, before committing
php artisan test tests/Feature/TenancyTest.php  # Existing tenant tests
php artisan test tests/Feature/DeadDrop/       # New tests
php artisan test                                # Full suite (safety check)
```

### 7. Document PostgreSQL Quirks
```php
// ✅ If using PostgreSQL DDL (CREATE DATABASE, DROP DATABASE, etc)
// PostgreSQL requires these to run outside transactions
if ($pdo->inTransaction()) {
    $pdo->commit();
}
$pdo->exec($ddlStatement);

// ❌ Never use statement() for PostgreSQL DDL in transactions
DB::connection('pgsql')->statement('CREATE DATABASE ...');
```

---

## Troubleshooting Common Issues

### Issue: "CREATE DATABASE cannot run inside a transaction block"
**Solution**: Commit transaction before executing DDL
```php
if ($pdo->inTransaction()) {
    $pdo->commit();
}
$pdo->exec('CREATE DATABASE "name"');
```

### Issue: Test passes but Dusk test fails
**Solution**: Browser context has different middleware/session behavior
- Verify authentication is working in Dusk
- Check if middleware is skipped in certain contexts
- Use `loginAs()` correctly in Dusk tests

### Issue: "Method does not exist" errors
**Solution**: Laravel connection APIs change between versions
- Check Laravel 12 documentation for correct method names
- Use `getPdo()` for raw PDO connection, not `getRawConnection()`
- Use `props()` for Inertia responses, not `viewHas()`

### Issue: New tests pass, existing tests break
**Solution**: You chose wrong fix approach
- Phase 2 may have identified wrong root cause
- Option 1 (minimal change) might break existing behavior
- Use Option 2 (auto-provisioning) if it affects multi-tenant setup
- Only proceed with fixes that don't break existing tests

---

## Related Documentation

- `app/Tenancy/TenantContext.php` - Multi-tenant context switching
- `app/Tenancy/TenantConnectionManager.php` - Database connection management
- `tests/DeadDropTestCase.php` - Base class for multi-tenant tests
- `tests/Feature/TenancyTest.php` - Existing tenant tests to verify no breakage
- `tests/Browser/` - Dusk browser tests for end-to-end verification

---

## When to Escalate

If after Phase 2 investigation you find:
- Fundamental architectural issue with multi-tenancy
- Issue affecting multiple unrelated features
- Tenancy traits need significant refactoring
- Database migration or schema changes needed

Then stop and schedule deeper review with user instead of implementing complex Phase 3 fix.
