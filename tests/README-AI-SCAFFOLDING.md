# Testing Patterns for AI-Generated Tests

This guide provides clear patterns for MetaCampaign AI to generate tests in the Stash/DeadDrop platform.

---

## Decision Tree: Which Test Base to Use?

### For Central Database Tests (Auth, Settings, Users, Domains)

**Use**: `TestCase` with `RefreshDatabase` trait

**Pattern**:
```php
<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;
    
    test('user can login', function () {
        $user = User::factory()->create();
        
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        
        $response->assertRedirect('/dashboard');
    });
}
```

### For Tenant Database Tests (Campaigns, Documents, Processors)

**Use**: `TestCase` with `SetUpsTenantDatabase` trait

**Pattern**:
```php
<?php

use Tests\TestCase;
use Tests\Concerns\SetUpsTenantDatabase;

class CampaignTest extends TestCase
{
    use SetUpsTenantDatabase;
    
    test('user can create campaign', function () {
        $tenant = $this->createTenant();
        $user = $this->createUserWithTenant($tenant);
        
        $this->inTenantContext($tenant, function () use ($user) {
            $campaign = Campaign::factory()->create();
            
            expect($campaign)->not->toBeNull();
            expect($campaign->getConnectionName())->toBe('tenant');
        });
    });
}
```

---

## Factory Usage Rules

### Central Models (no TenantContext needed)

These models use the `central` connection and can be created anywhere:

✅ **Correct Usage**:
```php
$tenant = Tenant::factory()->create();
$user = User::factory()->create();
$domain = Domain::factory()->create();
```

### Tenant Models (MUST use TenantContext)

These models use the `tenant` connection and require `TenantContext::run()`:

❌ **Wrong** - Will fail with "table not found":
```php
$campaign = Campaign::factory()->create();
```

✅ **Correct** - Using helper method:
```php
$campaign = $this->inTenantContext($tenant, fn() => 
    Campaign::factory()->create()
);
```

✅ **Correct** - Using TenantContext directly:
```php
$campaign = TenantContext::run($tenant, fn() => 
    Campaign::factory()->create()
);
```

---

## Helper Methods Available in TestCase

### `createTenant(array $attributes = []): Tenant`

Create a tenant with sensible defaults.

```php
// Basic usage
$tenant = $this->createTenant();

// With custom attributes
$tenant = $this->createTenant([
    'name' => 'Custom Organization',
    'tier' => 'enterprise',
]);
```

**Default values**:
- `name`: "Test Organization"
- `slug`: "test-{unique_id}"
- `email`: Fake email
- `tier`: "professional"
- `status`: "active"

### `createUserWithTenant(Tenant $tenant, array $attributes = []): User`

Create a user and associate with a tenant.

```php
$tenant = $this->createTenant();
$user = $this->createUserWithTenant($tenant);

// User is automatically attached to tenant with 'admin' role
expect($user->tenants)->toHaveCount(1);
```

### `inTenantContext(Tenant $tenant, callable $callback): mixed`

Execute code within a tenant's database context.

```php
$tenant = $this->createTenant();

$campaign = $this->inTenantContext($tenant, function () {
    return Campaign::factory()->create();
});

// Multiple operations in same context
$this->inTenantContext($tenant, function () {
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
    ]);
});
```

### `assertNoDatabaseErrors(TestResponse $response): void`

Assert response doesn't contain database errors.

```php
$response = $this->get('/campaigns');
$this->assertNoDatabaseErrors($response);
```

---

## Common Test Scenarios

### Scenario 1: Test authenticated route accessing tenant resource

```php
test('authenticated user can view campaign', function () {
    // 1. Create tenant and user
    $tenant = $this->createTenant();
    $user = $this->createUserWithTenant($tenant);
    
    // 2. Create resource in tenant context
    $campaign = $this->inTenantContext($tenant, fn() => 
        Campaign::factory()->create()
    );
    
    // 3. Make authenticated request
    $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}");
    
    // 4. Assert success
    $response->assertStatus(200);
    $this->assertNoDatabaseErrors($response);
})->uses(TestCase::class, SetUpsTenantDatabase::class);
```

### Scenario 2: Test model relationships within tenant

```php
test('campaign has documents', function () {
    $tenant = $this->createTenant();
    
    $this->inTenantContext($tenant, function () {
        // Create campaign with documents
        $campaign = Campaign::factory()->create();
        $documents = Document::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
        ]);
        
        // Assert relationship
        expect($campaign->documents)->toHaveCount(3);
        expect($campaign->documents->first()->campaign_id)->toBe($campaign->id);
    });
})->uses(TestCase::class, SetUpsTenantDatabase::class);
```

### Scenario 3: Test processor execution

```php
test('processor handles document', function () {
    $tenant = $this->createTenant();
    
    $this->inTenantContext($tenant, function () {
        // Setup
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
        ]);
        
        // Execute processor
        $processor = app(OcrProcessor::class);
        $result = $processor->process($document);
        
        // Assert
        expect($result)->not->toBeNull();
        expect($result->success)->toBeTrue();
    });
})->uses(TestCase::class, SetUpsTenantDatabase::class);
```

### Scenario 4: Test form submission

```php
test('user can create campaign via form', function () {
    $tenant = $this->createTenant();
    $user = $this->createUserWithTenant($tenant);
    
    $response = $this->actingAs($user)->post('/campaigns', [
        'name' => 'New Campaign',
        'description' => 'Test description',
        'type' => 'custom',
    ]);
    
    $response->assertRedirect();
    
    // Verify campaign was created in tenant database
    $this->inTenantContext($tenant, function () {
        expect(Campaign::where('name', 'New Campaign')->exists())->toBeTrue();
    });
})->uses(TestCase::class, SetUpsTenantDatabase::class);
```

### Scenario 5: Test API endpoint

```php
test('api returns campaigns list', function () {
    $tenant = $this->createTenant();
    $user = $this->createUserWithTenant($tenant);
    
    // Create campaigns in tenant
    $this->inTenantContext($tenant, function () {
        Campaign::factory()->count(5)->create();
    });
    
    // Request API
    $response = $this->actingAs($user)->getJson('/api/campaigns');
    
    $response->assertStatus(200);
    $response->assertJsonCount(5, 'data');
})->uses(TestCase::class, SetUpsTenantDatabase::class);
```

---

## Model Connection Reference

### Central Database Models

These models have `$connection = 'central'` and can be used anywhere:

- `Tenant` - Organization/subscriber
- `User` - Application users
- `Domain` - Tenant domains
- `StoredWorkflow` - Laravel Workflow state (system-level)

**Example**:
```php
// No tenant context needed
$tenant = Tenant::factory()->create();
$user = User::factory()->create();
```

### Tenant Database Models

These models have `$connection = 'tenant'` and **require** `TenantContext::run()`:

- `Campaign` - Document processing workflows
- `Document` - Uploaded documents
- `DocumentJob` - Processing jobs
- `Processor` - Processing steps
- `ProcessorExecution` - Execution records
- `Credential` - API credentials (tenant-level)
- `AuditLog` - Audit trail
- `UsageEvent` - Usage tracking
- `Contact` - Contact records
- `ContactMedia` - Contact media files
- `KycTransaction` - KYC transactions
- `CustomValidationRule` - Validation rules
- `PipelineProgress` - Pipeline tracking

**Example**:
```php
// MUST use tenant context
$tenant = $this->createTenant();
$campaign = $this->inTenantContext($tenant, fn() => 
    Campaign::factory()->create()
);
```

---

## How to Find Model Connection

### Method 1: Check model class for explicit connection

```php
class Campaign extends Model {
    protected $connection = 'tenant'; // ← This is a tenant model
}
```

### Method 2: Check for BelongsToTenant trait

```php
class Campaign extends Model {
    use BelongsToTenant; // ← This is a tenant model
}
```

### Method 3: No connection specified = central

```php
class User extends Model {
    // No $connection property or BelongsToTenant trait
    // ← This uses default 'central' connection
}
```

---

## Directory Structure for New Tests

```
tests/
├── Unit/                    # Unit tests (isolated, fast)
│   ├── Models/              # Model tests
│   ├── Services/            # Service class tests
│   └── Processors/          # Processor logic tests
├── Feature/                 # Feature tests (HTTP, integration)
│   ├── Auth/                # Authentication tests
│   ├── Settings/            # User settings tests
│   ├── Tenancy/             # Tenant-scoped feature tests
│   ├── Workflows/           # Workflow tests
│   └── Api/                 # API endpoint tests
└── Integration/             # Integration tests (E2E)
    ├── Pipelines/           # Pipeline integration tests
    └── Processors/          # Processor integration tests
```

### Placement Guidelines

**Unit Tests** → `tests/Unit/`
- Test single class/method
- Mock dependencies
- Fast execution
- No database preferred (or RefreshDatabase if needed)

**Feature Tests** → `tests/Feature/`
- Test HTTP endpoints
- Test user interactions
- Test form submissions
- Use RefreshDatabase or SetUpsTenantDatabase

**Integration Tests** → `tests/Integration/`
- Test multiple components together
- Test end-to-end workflows
- Test external service integration
- Use SetUpsTenantDatabase for tenant features

---

## Pest vs PHPUnit Syntax

Both are supported. Choose based on existing patterns in the directory.

### Pest (Recommended for new tests)

```php
test('example test', function () {
    $tenant = $this->createTenant();
    expect($tenant)->not->toBeNull();
})->uses(TestCase::class, RefreshDatabase::class);
```

### PHPUnit

```php
class ExampleTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_example(): void
    {
        $tenant = $this->createTenant();
        $this->assertNotNull($tenant);
    }
}
```

---

## Quick Reference Cheat Sheet

| Task | Central DB | Tenant DB |
|------|-----------|-----------|
| **Base Class** | `TestCase` | `TestCase` |
| **Trait** | `RefreshDatabase` | `SetUpsTenantDatabase` |
| **Create Model** | `Model::factory()->create()` | `$this->inTenantContext($tenant, fn() => Model::factory()->create())` |
| **Access Model** | Direct access | Must be in tenant context |
| **Examples** | User, Tenant, Domain | Campaign, Document, Processor |

---

## Error Messages to Watch For

### "Table not found" or "Undefined table"
**Cause**: Trying to access tenant model without tenant context  
**Fix**: Wrap in `$this->inTenantContext($tenant, fn() => ...)`

### "Connection not configured"
**Cause**: Missing `SetUpsTenantDatabase` trait  
**Fix**: Add `use SetUpsTenantDatabase` to test class

### "Unique constraint violation"
**Cause**: Not using RefreshDatabase or SetUpsTenantDatabase  
**Fix**: Add appropriate trait to clean database between tests

---

## Best Practices for AI-Generated Tests

1. ✅ **Always use TestCase helpers** instead of manual setup
2. ✅ **Use explicit connection checks** in assertions
3. ✅ **Use descriptive test names** (e.g., "user can create campaign")
4. ✅ **Group related assertions** together
5. ✅ **Use factories** instead of manual model creation
6. ✅ **Clean up resources** (handled automatically by traits)
7. ✅ **Test one thing per test** (single responsibility)

---

## Examples from Smoke Tests

See `tests/Feature/Smoke/EnvironmentSmokeTest.php` for working examples of all patterns.
