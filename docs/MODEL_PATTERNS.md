# Model Patterns & Anti-Patterns

This document clarifies correct patterns for working with models in the Stash/DeadDrop platform.

## Document Model: State vs Status

### ❌ WRONG - Do NOT use `status` field

```php
// WRONG - Document doesn't have a 'status' column
Document::where('status', 'pending')->count();
Document::where('status', 'completed')->count();
$document->status; // This field doesn't exist!
```

### ✅ CORRECT - Use `state` field with state classes

```php
use App\States\Document\PendingDocumentState;
use App\States\Document\QueuedDocumentState;
use App\States\Document\ProcessingDocumentState;
use App\States\Document\CompletedDocumentState;
use App\States\Document\FailedDocumentState;

// Querying by state
Document::whereState('state', PendingDocumentState::class)->count();
Document::whereState('state', CompletedDocumentState::class)->count();

// Processing includes both Queued and Processing states
Document::whereState('state', [
    QueuedDocumentState::class,
    ProcessingDocumentState::class
])->count();

// Using scopes
Document::pending()->count();
Document::completed()->count();
Document::failed()->count();

// Checking state
if ($document->state instanceof CompletedDocumentState) {
    // Document is completed
}

// Using helper methods
if ($document->isCompleted()) { }
if ($document->isFailed()) { }
if ($document->isProcessing()) { }

// Transitioning states
$document->toProcessing();
$document->toCompleted();
$document->toFailed();
```

### Document State Machine

The Document model uses **Spatie's Laravel Model States** package with these states:

- **PendingDocumentState** - Initial state after upload
- **QueuedDocumentState** - Queued for processing
- **ProcessingDocumentState** - Currently being processed
- **CompletedDocumentState** - Successfully processed
- **FailedDocumentState** - Processing failed
- **CancelledDocumentState** - Manually cancelled

### Database Schema

```php
Schema::create('documents', function (Blueprint $table) {
    // ... other fields
    $table->string('state'); // NOT 'status'!
    // ... other fields
});
```

## Campaign Model: Status Field

Campaigns **DO** use a `status` field (not a state machine):

```php
// ✅ CORRECT - Campaign has 'status' column
Campaign::where('status', 'active')->count();
Campaign::where('status', 'paused')->count();

// Valid status values
enum CampaignStatus: string {
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
```

## Multi-Tenancy: Custom Implementation

### ❌ WRONG - No stancl/tenancy package

```php
// WRONG - We don't use stancl/tenancy
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Database\Models\Tenant as StanclTenant;
```

### ✅ CORRECT - Custom tenancy system

```php
use App\Tenancy\TenantContext;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\Traits\BelongsToTenant;
use App\Models\Tenant; // Our custom Tenant model
```

### Key Differences

| Feature | stancl/tenancy | Our Custom System |
|---------|---------------|-------------------|
| **Tenant Model** | `Stancl\Tenancy\Database\Models\Tenant` | `App\Models\Tenant` |
| **Context Management** | `tenancy()->initialize($tenant)` | `TenantContext::initialize($tenant)` |
| **Model Trait** | `BelongsToCentralDomain` | `BelongsToTenant` |
| **Middleware** | `InitializeTenancyByDomain` | `InitializeTenantFromUser` |
| **Connection Switching** | Automatic | Via `TenantConnectionManager` |

### How Our Tenancy Works

1. **User logs in** → Central database (`pgsql` connection)
2. **Middleware runs** → `InitializeTenantFromUser` reads `user->tenant_id`
3. **Context initialized** → `TenantContext::initialize($tenant)` sets up connection
4. **Models query tenant DB** → Models with `BelongsToTenant` trait use `tenant` connection

### Example Usage

```php
// Initialize tenant context (usually done by middleware)
$user = Auth::user();
$tenant = Tenant::on('pgsql')->find($user->tenant_id);
TenantContext::initialize($tenant);

// Now tenant-scoped models query the tenant database
$campaigns = Campaign::all(); // Queries tenant DB
$documents = Document::all(); // Queries tenant DB

// Clean up context when done
TenantContext::forgetCurrent();
```

## State Machine Models

These models use **state machines** (not status enums):

- **Document** - Uses `state` column
- **DocumentJob** - Uses `state` column  
- **ProcessorExecution** - Uses `state` column

Always use `whereState()` and state class FQCNs when querying.

## Status Enum Models

These models use **status enums** (not state machines):

- **Campaign** - Uses `status` column
- **Tenant** - Uses `status` column

Use normal `where('status', ...)` queries.

## Testing Patterns

### Testing Document States

```php
use App\States\Document\PendingDocumentState;

test('document initializes with pending state', function () {
    $document = Document::factory()->create();
    
    expect($document->state)->toBeInstanceOf(PendingDocumentState::class);
});

test('document can transition to completed', function () {
    $document = Document::factory()->create();
    
    $document->toCompleted();
    
    expect($document->state)->toBeInstanceOf(CompletedDocumentState::class);
    expect($document->processed_at)->not->toBeNull();
});
```

### Testing with Tenant Context

```php
use Tests\DeadDropTestCase;

test('campaigns are tenant-scoped', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    TenantContext::run($tenant1, function () {
        Campaign::factory()->count(3)->create();
    });
    
    TenantContext::run($tenant2, function () {
        Campaign::factory()->count(2)->create();
        
        // Only sees tenant2's campaigns
        expect(Campaign::count())->toBe(2);
    });
})->uses(DeadDropTestCase::class);
```

## Common Mistakes

### ❌ Using status for Documents

```php
// WRONG
$pending = Document::where('status', 'pending')->get();
```

### ❌ Using state for Campaigns

```php
// WRONG  
$active = Campaign::whereState('state', ActiveCampaignState::class)->get();
```

### ❌ Importing stancl/tenancy classes

```php
// WRONG
use Stancl\Tenancy\Database\Models\Tenant;
```

### ❌ Forgetting tenant context in tests

```php
// WRONG - Will fail with "relation does not exist"
test('create campaign', function () {
    $campaign = Campaign::factory()->create(); // No tenant context!
});

// CORRECT
test('create campaign', function () {
    $tenant = Tenant::factory()->create();
    
    TenantContext::run($tenant, function () {
        $campaign = Campaign::factory()->create();
        expect($campaign)->not->toBeNull();
    });
});
```

## Quick Reference

| Model | State/Status | Query Method | Column Name |
|-------|-------------|--------------|-------------|
| **Document** | State Machine | `whereState()` | `state` |
| **DocumentJob** | State Machine | `whereState()` | `state` |
| **ProcessorExecution** | State Machine | `whereState()` | `state` |
| **Campaign** | Status Enum | `where('status')` | `status` |
| **Tenant** | Status Enum | `where('status')` | `status` |

## See Also

- [State Machine Implementation](../STATE_MACHINE_IMPLEMENTATION.md)
- [Custom Tenancy System](../CUSTOM_TENANCY.md)
- [Testing Guide](../tests/README.md)
