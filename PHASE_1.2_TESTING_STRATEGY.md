# Phase 1.2: Comprehensive Testing Strategy

## Overview

Comprehensive unit and integration tests for the Stash/DeadDrop database schema with special focus on **multi-tenancy isolation** and **edge cases**.

**Goal**: 100% confidence that tenant data never leaks across boundaries.

---

## Test Organization

```
tests/
├── Unit/
│   └── DeadDrop/
│       ├── Models/
│       │   ├── TenantTest.php (15 tests)
│       │   ├── UserTest.php (12 tests)
│       │   ├── CampaignTest.php (18 tests)
│       │   ├── DocumentTest.php (20 tests)
│       │   ├── DocumentJobTest.php (15 tests)
│       │   ├── ProcessorTest.php (10 tests)
│       │   ├── ProcessorExecutionTest.php (12 tests)
│       │   ├── CredentialTest.php (25 tests)
│       │   ├── UsageEventTest.php (10 tests)
│       │   └── AuditLogTest.php (8 tests)
│       └── Factories/
│           └── FactoryValidityTest.php (20 tests)
├── Feature/
│   └── DeadDrop/
│       └── Tenancy/
│           ├── TenantIsolationTest.php (30 tests)
│           ├── TenantSwitchingTest.php (15 tests)
│           ├── ConcurrentTenantAccessTest.php (10 tests)
│           └── CredentialHierarchyTest.php (20 tests)
└── Integration/
    └── DeadDrop/
        ├── CompleteCampaignLifecycleTest.php (15 tests)
        ├── MultiTenantScenarios.php (25 tests)
        ├── CrossTenantLeakPreventionTest.php (20 tests)
        └── TenantDataMigrationTest.php (10 tests)
```

**Total Estimated Tests**: ~280 tests

---

## Unit Tests: Models

### 1. TenantTest.php (15 tests)

**Purpose**: Test Tenant model with stancl/tenancy integration

```php
test('tenant has ulid primary key', function () {
    $tenant = Tenant::factory()->create();
    
    expect($tenant->id)->toBeString()
        ->and(strlen($tenant->id))->toBe(26); // ULID length
});

test('tenant slug is unique', function () {
    Tenant::factory()->create(['slug' => 'acme']);
    
    expect(fn() => Tenant::factory()->create(['slug' => 'acme']))
        ->toThrow(QueryException::class);
});

test('tenant can have users', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    expect($tenant->users)->toHaveCount(1)
        ->and($tenant->users->first()->id)->toBe($user->id);
});

test('tenant credentials are encrypted', function () {
    $credentials = ['openai_key' => 'sk-test123'];
    $tenant = Tenant::factory()->create(['credentials' => encrypt(json_encode($credentials))]);
    
    $decrypted = json_decode(decrypt($tenant->credentials), true);
    expect($decrypted['openai_key'])->toBe('sk-test123');
});

test('tenant status transitions are valid', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    
    $tenant->update(['status' => 'suspended']);
    expect($tenant->status)->toBe('suspended');
    
    $tenant->update(['status' => 'cancelled']);
    expect($tenant->status)->toBe('cancelled');
});

test('tenant credit balance can be adjusted', function () {
    $tenant = Tenant::factory()->create(['credit_balance' => 1000]);
    
    $tenant->increment('credit_balance', 500);
    expect($tenant->fresh()->credit_balance)->toBe(1500);
    
    $tenant->decrement('credit_balance', 300);
    expect($tenant->fresh()->credit_balance)->toBe(1200);
});

test('tenant trial expiration works', function () {
    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(7),
    ]);
    
    expect($tenant->trial_ends_at->isFuture())->toBeTrue();
    
    $expired = Tenant::factory()->create([
        'trial_ends_at' => now()->subDays(1),
    ]);
    
    expect($expired->trial_ends_at->isPast())->toBeTrue();
});

test('tenant soft deletes work', function () {
    $tenant = Tenant::factory()->create();
    $tenantId = $tenant->id;
    
    $tenant->delete();
    
    expect(Tenant::find($tenantId))->toBeNull()
        ->and(Tenant::withTrashed()->find($tenantId))->not->toBeNull();
});

test('tenant settings json cast works', function () {
    $settings = ['default_ai' => 'openai', 'max_uploads' => 100];
    $tenant = Tenant::factory()->create(['settings' => $settings]);
    
    expect($tenant->settings)->toBeArray()
        ->and($tenant->settings['default_ai'])->toBe('openai');
});

test('tenant tier enum is enforced', function () {
    $tenant = Tenant::factory()->create(['tier' => 'starter']);
    expect($tenant->tier)->toBe('starter');
    
    $tenant->update(['tier' => 'enterprise']);
    expect($tenant->tier)->toBe('enterprise');
    
    expect(fn() => Tenant::factory()->create(['tier' => 'invalid']))
        ->toThrow(Exception::class);
});

// ... 5 more tests for edge cases
```

### 2. UserTest.php (12 tests)

```php
test('user belongs to tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    expect($user->tenant)->toBeInstanceOf(Tenant::class)
        ->and($user->tenant->id)->toBe($tenant->id);
});

test('user cannot be created without tenant', function () {
    expect(fn() => User::factory()->create(['tenant_id' => null]))
        ->toThrow(QueryException::class);
});

test('user role enum is enforced', function () {
    $tenant = Tenant::factory()->create();
    
    foreach (['owner', 'admin', 'member', 'viewer'] as $role) {
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $role]);
        expect($user->role)->toBe($role);
    }
});

test('user permissions json cast works', function () {
    $tenant = Tenant::factory()->create();
    $permissions = ['campaigns.create', 'documents.view'];
    
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'permissions' => $permissions,
    ]);
    
    expect($user->permissions)->toBeArray()
        ->and($user->permissions)->toContain('campaigns.create');
});

test('user cascade deletes with tenant', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    $tenant->delete();
    
    expect(User::find($user->id))->toBeNull();
});

// ... 7 more tests
```

### 3. CampaignTest.php (18 tests)

```php
test('campaign belongs to tenant via BelongsToTenant trait', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    
    expect($campaign->tenant_id)->toBe($tenant->id);
});

test('campaign pipeline config json cast works', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $pipelineConfig = [
        'processors' => [
            ['id' => 'ocr', 'type' => 'OCRProcessor'],
            ['id' => 'classify', 'type' => 'ClassifierProcessor'],
        ],
    ];
    
    $campaign = Campaign::factory()->create([
        'pipeline_config' => $pipelineConfig,
    ]);
    
    expect($campaign->pipeline_config)->toBeArray()
        ->and($campaign->pipeline_config['processors'])->toHaveCount(2);
});

test('campaign slug is unique per tenant', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    Campaign::factory()->create(['slug' => 'invoice-processing']);
    
    expect(fn() => Campaign::factory()->create(['slug' => 'invoice-processing']))
        ->toThrow(QueryException::class);
});

test('campaigns from different tenants can have same slug', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    $campaign1 = Campaign::factory()->create(['slug' => 'same-slug']);
    
    tenancy()->initialize($tenant2);
    $campaign2 = Campaign::factory()->create(['slug' => 'same-slug']);
    
    expect($campaign1->slug)->toBe($campaign2->slug)
        ->and($campaign1->tenant_id)->not->toBe($campaign2->tenant_id);
});

test('campaign type meta is reserved for Meta-Campaign', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $metaCampaign = Campaign::factory()->create(['type' => 'meta']);
    
    expect($metaCampaign->type)->toBe('meta');
});

test('campaign credentials override tenant credentials', function () {
    $tenant = Tenant::factory()->create([
        'credentials' => encrypt(json_encode(['openai_key' => 'tenant-key'])),
    ]);
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create([
        'credentials' => encrypt(json_encode(['openai_key' => 'campaign-key'])),
    ]);
    
    expect($campaign->credentials)->not->toBeNull();
});

test('campaign max concurrent jobs default is 10', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    
    expect($campaign->max_concurrent_jobs)->toBe(10);
});

test('campaign retention days default is 90', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    
    expect($campaign->retention_days)->toBe(90);
});

test('campaign status transitions are valid', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create(['status' => 'draft']);
    
    $campaign->update(['status' => 'active']);
    expect($campaign->status)->toBe('active');
    
    $campaign->update(['status' => 'paused']);
    expect($campaign->status)->toBe('paused');
});

test('campaign has many documents', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $documents = Document::factory()->count(5)->create(['campaign_id' => $campaign->id]);
    
    expect($campaign->documents)->toHaveCount(5);
});

// ... 8 more tests for checklist template, published_at, etc.
```

### 4. DocumentTest.php (20 tests)

```php
test('document has uuid for public identification', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create(['campaign_id' => $campaign->id]);
    
    expect($document->uuid)->toBeString()
        ->and(Str::isUuid($document->uuid))->toBeTrue();
});

test('document belongs to campaign and tenant', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create(['campaign_id' => $campaign->id]);
    
    expect($document->campaign)->toBeInstanceOf(Campaign::class)
        ->and($document->campaign->id)->toBe($campaign->id)
        ->and($document->tenant_id)->toBe($tenant->id);
});

test('document status lifecycle', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'pending',
    ]);
    
    $document->update(['status' => 'queued']);
    expect($document->status)->toBe('queued');
    
    $document->update(['status' => 'processing']);
    expect($document->status)->toBe('processing');
    
    $document->update(['status' => 'completed', 'processed_at' => now()]);
    expect($document->status)->toBe('completed')
        ->and($document->processed_at)->not->toBeNull();
});

test('document storage path includes tenant id', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create(['campaign_id' => $campaign->id]);
    
    expect($document->storage_path)->toContain($tenant->id);
});

test('document hash is sha256', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'hash' => hash('sha256', 'test content'),
    ]);
    
    expect(strlen($document->hash))->toBe(64); // SHA-256 hex length
});

test('document metadata json cast works', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $metadata = ['extracted_text' => 'Sample text', 'classification' => 'invoice'];
    
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'metadata' => $metadata,
    ]);
    
    expect($document->metadata)->toBeArray()
        ->and($document->metadata['classification'])->toBe('invoice');
});

test('document processing history tracks pipeline stages', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $history = [
        ['processor' => 'ocr', 'status' => 'completed', 'timestamp' => now()],
        ['processor' => 'classify', 'status' => 'completed', 'timestamp' => now()],
    ];
    
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'processing_history' => $history,
    ]);
    
    expect($document->processing_history)->toBeArray()
        ->and($document->processing_history)->toHaveCount(2);
});

test('document retry count increments', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'retry_count' => 0,
    ]);
    
    $document->increment('retry_count');
    expect($document->fresh()->retry_count)->toBe(1);
});

test('document size bytes is bigint', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'size_bytes' => 5368709120, // 5GB
    ]);
    
    expect($document->size_bytes)->toBe(5368709120);
});

test('document belongs to user who uploaded', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'user_id' => $user->id,
    ]);
    
    expect($document->user)->toBeInstanceOf(User::class)
        ->and($document->user->id)->toBe($user->id);
});

// ... 10 more tests for failed_at, error_message, soft deletes, etc.
```

### 5. CredentialTest.php (25 tests) - CRITICAL for Security

```php
test('credential value is encrypted at rest', function () {
    $credential = Credential::factory()->create([
        'scope_type' => 'system',
        'key' => 'openai_key',
        'value' => 'sk-test123',
    ]);
    
    // Value should be encrypted in database
    $raw = DB::table('credentials')->where('id', $credential->id)->first();
    expect($raw->value)->not->toBe('sk-test123');
    
    // But decrypted via model accessor
    expect($credential->value)->toBe('sk-test123');
});

test('credential scope hierarchy: system < tenant < campaign < processor', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $processor = Processor::factory()->create();
    
    $systemCred = Credential::factory()->create([
        'scope_type' => 'system',
        'scope_id' => null,
        'key' => 'openai_key',
        'value' => 'system-key',
    ]);
    
    $tenantCred = Credential::factory()->create([
        'scope_type' => 'tenant',
        'scope_id' => $tenant->id,
        'key' => 'openai_key',
        'value' => 'tenant-key',
    ]);
    
    $campaignCred = Credential::factory()->create([
        'scope_type' => 'campaign',
        'scope_id' => $campaign->id,
        'key' => 'openai_key',
        'value' => 'campaign-key',
    ]);
    
    // Test credential resolution (mock CredentialVault service)
    // Campaign-level should take precedence
    expect($campaignCred->value)->toBe('campaign-key');
});

test('credential scope_type enum is enforced', function () {
    expect(fn() => Credential::factory()->create(['scope_type' => 'invalid']))
        ->toThrow(Exception::class);
});

test('credential key is unique per scope', function () {
    $tenant = Tenant::factory()->create();
    
    Credential::factory()->create([
        'scope_type' => 'tenant',
        'scope_id' => $tenant->id,
        'key' => 'openai_key',
    ]);
    
    expect(fn() => Credential::factory()->create([
        'scope_type' => 'tenant',
        'scope_id' => $tenant->id,
        'key' => 'openai_key',
    ]))->toThrow(QueryException::class);
});

test('credentials from different scopes can have same key', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $cred1 = Credential::factory()->create([
        'scope_type' => 'tenant',
        'scope_id' => $tenant1->id,
        'key' => 'same_key',
    ]);
    
    $cred2 = Credential::factory()->create([
        'scope_type' => 'tenant',
        'scope_id' => $tenant2->id,
        'key' => 'same_key',
    ]);
    
    expect($cred1->key)->toBe($cred2->key)
        ->and($cred1->scope_id)->not->toBe($cred2->scope_id);
});

test('credential expiration works', function () {
    $credential = Credential::factory()->create([
        'expires_at' => now()->addDays(30),
    ]);
    
    expect($credential->expires_at->isFuture())->toBeTrue();
    
    $expired = Credential::factory()->create([
        'expires_at' => now()->subDays(1),
    ]);
    
    expect($expired->expires_at->isPast())->toBeTrue();
});

test('credential last_used_at updates', function () {
    $credential = Credential::factory()->create(['last_used_at' => null]);
    
    $credential->update(['last_used_at' => now()]);
    
    expect($credential->last_used_at)->not->toBeNull();
});

test('credential provider metadata works', function () {
    $metadata = ['rate_limit' => 100, 'endpoint' => 'https://api.openai.com'];
    
    $credential = Credential::factory()->create([
        'provider' => 'openai',
        'metadata' => $metadata,
    ]);
    
    expect($credential->metadata)->toBeArray()
        ->and($credential->metadata['rate_limit'])->toBe(100);
});

test('credential soft deletes work', function () {
    $credential = Credential::factory()->create();
    $credId = $credential->id;
    
    $credential->delete();
    
    expect(Credential::find($credId))->toBeNull()
        ->and(Credential::withTrashed()->find($credId))->not->toBeNull();
});

test('credential can be activated and deactivated', function () {
    $credential = Credential::factory()->create(['is_active' => true]);
    
    $credential->update(['is_active' => false]);
    expect($credential->is_active)->toBeFalse();
    
    $credential->update(['is_active' => true]);
    expect($credential->is_active)->toBeTrue();
});

// ... 15 more tests for edge cases, encryption failures, null scopes, etc.
```

---

## Integration Tests: Tenancy

### TenantIsolationTest.php (30 tests)

**Critical Tests for Multi-Tenancy Security**

```php
test('campaigns are isolated between tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    $campaign1 = Campaign::factory()->create(['name' => 'Tenant 1 Campaign']);
    
    tenancy()->initialize($tenant2);
    $campaign2 = Campaign::factory()->create(['name' => 'Tenant 2 Campaign']);
    
    // Tenant 2 should only see their campaign
    expect(Campaign::count())->toBe(1)
        ->and(Campaign::first()->name)->toBe('Tenant 2 Campaign');
    
    // Switch back to tenant 1
    tenancy()->initialize($tenant1);
    expect(Campaign::count())->toBe(1)
        ->and(Campaign::first()->name)->toBe('Tenant 1 Campaign');
});

test('documents cannot be accessed across tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    $campaign1 = Campaign::factory()->create();
    $doc1 = Document::factory()->create(['campaign_id' => $campaign1->id]);
    
    tenancy()->initialize($tenant2);
    
    // Tenant 2 should not see tenant 1's documents
    expect(Document::count())->toBe(0)
        ->and(Document::find($doc1->id))->toBeNull();
});

test('credential resolution respects tenant scope', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    // Tenant 1 has openai key
    Credential::factory()->create([
        'scope_type' => 'tenant',
        'scope_id' => $tenant1->id,
        'key' => 'openai_key',
        'value' => 'tenant1-key',
    ]);
    
    // Tenant 2 has different key
    Credential::factory()->create([
        'scope_type' => 'tenant',
        'scope_id' => $tenant2->id,
        'key' => 'openai_key',
        'value' => 'tenant2-key',
    ]);
    
    tenancy()->initialize($tenant1);
    $cred1 = Credential::where('scope_type', 'tenant')->first();
    expect($cred1->value)->toBe('tenant1-key');
    
    tenancy()->initialize($tenant2);
    $cred2 = Credential::where('scope_type', 'tenant')->first();
    expect($cred2->value)->toBe('tenant2-key');
});

test('usage events are tenant-scoped', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    $campaign1 = Campaign::factory()->create();
    UsageEvent::factory()->count(5)->create(['campaign_id' => $campaign1->id]);
    
    tenancy()->initialize($tenant2);
    $campaign2 = Campaign::factory()->create();
    UsageEvent::factory()->count(3)->create(['campaign_id' => $campaign2->id]);
    
    // Tenant 2 should only see their 3 events
    expect(UsageEvent::count())->toBe(3);
    
    tenancy()->initialize($tenant1);
    expect(UsageEvent::count())->toBe(5);
});

test('audit logs are tenant-isolated', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    AuditLog::factory()->count(10)->create();
    
    tenancy()->initialize($tenant2);
    AuditLog::factory()->count(5)->create();
    
    expect(AuditLog::count())->toBe(5);
});

test('raw queries without tenant context fail safely', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    Campaign::factory()->create();
    
    tenancy()->initialize($tenant2);
    Campaign::factory()->create();
    
    // Raw query without tenant scoping should only return tenant 2's campaign
    $count = DB::table('campaigns')->count();
    expect($count)->toBe(1);
});

test('filesystem is tenant-aware', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    Storage::disk('local')->put('test.txt', 'Tenant 1 content');
    
    tenancy()->initialize($tenant2);
    Storage::disk('local')->put('test.txt', 'Tenant 2 content');
    
    // Both files exist but in different tenant directories
    expect(Storage::disk('local')->get('test.txt'))->toBe('Tenant 2 content');
    
    tenancy()->initialize($tenant1);
    expect(Storage::disk('local')->get('test.txt'))->toBe('Tenant 1 content');
});

test('cache is tenant-scoped', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    Cache::put('key', 'tenant1-value', 60);
    
    tenancy()->initialize($tenant2);
    Cache::put('key', 'tenant2-value', 60);
    
    expect(Cache::get('key'))->toBe('tenant2-value');
    
    tenancy()->initialize($tenant1);
    expect(Cache::get('key'))->toBe('tenant1-value');
});

test('queued jobs are tenant-aware', function () {
    Queue::fake();
    
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create(['campaign_id' => $campaign->id]);
    
    // Dispatch job (example: ProcessDocumentJob)
    // Job should have tenant context
    
    Queue::assertPushed(function ($job) use ($tenant) {
        return property_exists($job, 'tenantId') && $job->tenantId === $tenant->id;
    });
});

test('eloquent relationships are tenant-scoped', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    tenancy()->initialize($tenant1);
    $campaign1 = Campaign::factory()->create();
    Document::factory()->count(5)->create(['campaign_id' => $campaign1->id]);
    
    tenancy()->initialize($tenant2);
    $campaign2 = Campaign::factory()->create();
    Document::factory()->count(3)->create(['campaign_id' => $campaign2->id]);
    
    // Campaign 2 should only see its 3 documents
    expect($campaign2->documents)->toHaveCount(3);
    
    tenancy()->initialize($tenant1);
    expect($campaign1->documents)->toHaveCount(5);
});

// ... 20 more edge case tests
```

---

## Success Criteria

✅ **280+ tests passing**  
✅ **100% model test coverage**  
✅ **Zero cross-tenant data leaks**  
✅ **All edge cases covered**  
✅ **Concurrent access tested**  
✅ **Credential hierarchy validated**  
✅ **Filesystem isolation verified**  
✅ **Cache isolation verified**  
✅ **Queue tenant context tested**  
✅ **Factory validity confirmed**  
✅ **Test execution < 60 seconds**  
✅ **No flaky tests**

---

## Implementation Order

**Day 1-2**: Unit tests for all 10 models (145 tests)  
**Day 3**: Factory validity tests (20 tests)  
**Day 4-5**: Tenant isolation tests (65 tests)  
**Day 6**: Integration tests (50 tests)  
**Day 7**: Refine, fix flaky tests, optimize

---

## Testing Commands

```bash
# Run all Phase 1.2 tests
php artisan test tests/Unit/DeadDrop tests/Feature/DeadDrop tests/Integration/DeadDrop

# Run only tenancy tests
php artisan test tests/Feature/DeadDrop/Tenancy

# Run with coverage
php artisan test --coverage --min=100

# Run specific test file
php artisan test tests/Feature/DeadDrop/Tenancy/TenantIsolationTest.php
```

Ready to implement? 🚀
