# Testing Rationalization Plan: Escrow and Rebuild Strategy

**Status**: Phase 1 - Ready to Begin  
**Current State**: 417 passing tests, 109 skipped, 1 warning  
**Goal**: Clean test structure with unified base class for MetaCampaign AI scaffolding

## Strategy Overview

1. **Escrow**: Move all existing tests to `tests-legacy/` (excluded from test runs)
2. **Build Clean Environment**: Create new structure with helpers and traits
3. **Verify Empty State**: Confirm new environment works with smoke tests
4. **Gradually Reintroduce**: Move tests back into proper directories (Unit/Feature/Integration)
5. **Clean Up**: Remove legacy code once migration complete

---

## Phase 1: Escrow All Existing Tests

**Goal**: Move all tests to safe location, start with clean slate.

### Actions
1. Create `tests-legacy/` directory
2. Move ALL test files from `tests/` to `tests-legacy/` (preserve structure)
3. Keep only:
   - `tests/TestCase.php` (will be rewritten)
   - `tests/Pest.php` (Pest configuration)
   - `tests/.gitignore`
4. Add `/tests-legacy/` to `.gitignore`
5. Verify: `php artisan test` should run 0 tests
6. Commit "Phase 1: Escrow all tests to tests-legacy/"

### Expected State After Phase 1
```
tests/
  ├── TestCase.php          (kept)
  ├── Pest.php              (kept)
  └── .gitignore            (kept)
  
tests-legacy/              (excluded from test runs)
  ├── Unit/
  ├── Feature/
  ├── Integration/
  ├── Browser/
  ├── DeadDropTestCase.php
  ├── TenantAwareTestCase.php
  └── ... (417 passing tests escrowed)
```

---

## Phase 2: Build Clean Test Environment

**Goal**: Create new base classes, traits, and helpers from scratch.

### Actions
1. Rewrite `tests/TestCase.php` as clean base (with helpers)
2. Create `tests/Concerns/SetUpsTenantDatabase.php` trait
3. Create `tests/Fixtures/` directory for shared test data
4. Verify empty environment compiles
5. Commit "Phase 2: Create clean test environment"

### New `tests/TestCase.php`
```php
<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a tenant with default attributes.
     */
    protected function createTenant(array $attributes = []): Tenant
    {
        return Tenant::on('central')->create(array_merge([
            'name' => 'Test Organization',
            'slug' => 'test-' . uniqid(),
            'email' => fake()->email(),
            'tier' => 'professional',
            'status' => 'active',
        ], $attributes));
    }

    /**
     * Create a user associated with a tenant.
     */
    protected function createUserWithTenant(Tenant $tenant, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        return $user;
    }

    /**
     * Run a callback within tenant context.
     */
    protected function inTenantContext(Tenant $tenant, callable $callback): mixed
    {
        return TenantContext::run($tenant, $callback);
    }

    /**
     * Assert response has no database errors.
     */
    protected function assertNoDatabaseErrors(TestResponse $response): void
    {
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('Undefined table');
        $response->assertDontSee('does not exist');
    }
}
```

### New `tests/Concerns/SetUpsTenantDatabase.php`
```php
<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait SetUpsTenantDatabase
{
    use RefreshDatabase;

    /**
     * Define database connections to refresh and transact.
     */
    protected array $connectionsToTransact = ['central', 'tenant'];

    /**
     * Run tenant migrations after refreshing central database.
     */
    protected function afterRefreshingDatabase(): void
    {
        $this->artisan('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
}
```

---

## Phase 3: Create Smoke Tests to Verify Environment

**Goal**: Verify new environment works correctly before reintroducing tests.

### Actions
1. Create `tests/Unit/` directory
2. Create `tests/Feature/` directory
3. Create `tests/Integration/` directory
4. Create smoke test: `tests/Feature/Smoke/EnvironmentSmokeTest.php`
5. Run tests: Should see 5 passing smoke tests
6. Commit "Phase 3: Verify clean environment with smoke tests"

### `tests/Feature/Smoke/EnvironmentSmokeTest.php`
```php
<?php

namespace Tests\Feature\Smoke;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

describe('Clean Test Environment', function () {
    test('can create user on central database', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);
        
        expect($user)->not->toBeNull();
        expect($user->email)->toBe('test@example.com');
        expect($user->getConnectionName())->toBe('central');
    })->uses(RefreshDatabase::class);

    test('can create tenant with helper', function () {
        $tenant = $this->createTenant(['name' => 'Smoke Test Org']);
        
        expect($tenant)->not->toBeNull();
        expect($tenant->name)->toBe('Smoke Test Org');
        expect($tenant->slug)->toContain('test-');
    })->uses(RefreshDatabase::class);

    test('can create user with tenant helper', function () {
        $tenant = $this->createTenant();
        $user = $this->createUserWithTenant($tenant);
        
        expect($user)->not->toBeNull();
        expect($user->tenants)->toHaveCount(1);
        expect($user->tenants->first()->id)->toBe($tenant->id);
    })->uses(RefreshDatabase::class);

    test('can create campaign in tenant context', function () {
        $tenant = $this->createTenant();
        
        $campaign = $this->inTenantContext($tenant, function () {
            return Campaign::factory()->create(['name' => 'Smoke Test Campaign']);
        });
        
        expect($campaign)->not->toBeNull();
        expect($campaign->name)->toBe('Smoke Test Campaign');
        expect($campaign->getConnectionName())->toBe('tenant');
    })->uses(SetUpsTenantDatabase::class);

    test('tenant database migrations run successfully', function () {
        $tenant = $this->createTenant();
        
        $this->inTenantContext($tenant, function () {
            $campaigns = Campaign::all();
            expect($campaigns)->toBeCollection();
        });
    })->uses(SetUpsTenantDatabase::class);
});
```

---

## Phase 4: Document Testing Patterns for AI Scaffolding

**Goal**: Create clear guidelines for MetaCampaign test generation.

### Actions
1. Create `tests/README-AI-SCAFFOLDING.md`
2. Document decision tree, factory rules, scenarios, model connections
3. Commit "Phase 4: Document testing patterns for AI scaffolding"

(See full content in plan - includes decision tree, factory usage rules, common scenarios, and model connection reference)

---

## Phase 5: Gradually Reintroduce Tests

**Goal**: Move tests from `tests-legacy/` into proper Unit/Feature/Integration directories.

### Test Categories

- **Unit Tests** (~50): Model, service, helper tests → `tests/Unit/`
- **Feature Tests** (~300): HTTP, routes, workflows → `tests/Feature/`
- **Integration Tests** (~60): End-to-end pipelines → `tests/Integration/`

### Reintroduction Order

**Batch 1: Unit tests** (~50 tests)
- Move: `tests-legacy/Unit/` → `tests/Unit/`
- Update: `extends TestCase`, add `RefreshDatabase` where needed
- Run: `php artisan test tests/Unit/`

**Batch 2: Central feature tests** (~100 tests)
- Move: Auth, Settings from legacy
- Update: Keep `use RefreshDatabase`
- Run: `php artisan test tests/Feature/Auth tests/Feature/Settings`

**Batch 3: Tenant feature tests** (~150 tests)
- Move: `tests-legacy/Feature/DeadDrop/` → `tests/Feature/Tenancy/`
- Update: Use `SetUpsTenantDatabase`, replace manual setup with helpers
- Run: `php artisan test tests/Feature/Tenancy/`

**Batch 4: Integration tests** (~60 tests)
- Move: Pipeline/processor integration tests
- Update: Use `SetUpsTenantDatabase` for multi-tenant
- Run: `php artisan test tests/Integration/`

**Batch 5: Workflow tests** (~40 tests)
- Move: Workflow tests from legacy
- Update: Use helpers, `SetUpsTenantDatabase`
- Run: `php artisan test tests/Feature/Workflows/`

**Batch 6: Remaining tests** (~17 tests)
- Move: API, Notifications, etc.
- Run: `php artisan test` (full suite)

---

## Phase 6: Clean Up and Final Verification

**Goal**: Remove legacy code, verify everything works.

### Actions
1. Delete `tests-legacy/` directory
2. Delete any remaining legacy test case files
3. Remove `/tests-legacy/` from `.gitignore`
4. Run full test suite: `php artisan test`
5. Verify: 417+ passing, 109 skipped, 0 failed
6. Commit "Phase 6: Remove legacy tests, clean environment complete"

### Final Directory Structure
```
tests/
  ├── Unit/
  │   ├── Models/
  │   ├── Services/
  │   └── Processors/
  ├── Feature/
  │   ├── Auth/
  │   ├── Settings/
  │   ├── Tenancy/       (tenant-scoped tests)
  │   ├── Workflows/
  │   ├── Notifications/
  │   ├── Smoke/
  │   └── ... (other features)
  ├── Integration/
  │   ├── Pipelines/
  │   ├── Processors/
  │   └── Production/
  ├── Concerns/
  │   └── SetUpsTenantDatabase.php
  ├── Fixtures/         (shared test data)
  ├── TestCase.php      (unified base)
  ├── Pest.php
  ├── README-AI-SCAFFOLDING.md
  └── .gitignore
```

---

## Success Criteria

1. ✅ **Single Test Base**: All tests extend `TestCase`, multi-tenant support via trait
2. ✅ **Helper Methods**: Common patterns extracted into helpers
3. ✅ **Clear Documentation**: AI scaffolding guide with examples
4. ✅ **No Regressions**: All 417 tests still passing after migration
5. ✅ **Explicit Patterns**: Model connections documented
6. ✅ **Clean Structure**: Proper Unit/Feature/Integration organization

---

## Benefits for MetaCampaign

- **Predictable Structure**: Single test base + trait = simple generation logic
- **Helper Methods**: AI can use `$this->createTenant()` instead of boilerplate
- **Clear Rules**: Model connection reference guides factory usage
- **Examples**: Common scenarios provide templates
- **Type Safety**: Helpers have explicit return types
