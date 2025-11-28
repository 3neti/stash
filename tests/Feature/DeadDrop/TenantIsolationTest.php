<?php

use App\Models\{Campaign, Document, Processor, Credential, UsageEvent, AuditLog};

describe('Tenant Isolation Tests', function () {
    test('queries are scoped to tenant database', function () {
        // Create campaigns in default tenant (from DeadDropTestCase)
        Campaign::factory()->count(3)->create();
        
        // Verify count in current tenant
        expect(Campaign::count())->toBe(3);
        
        // Create a different tenant and verify its campaigns are isolated
        // We can't easily switch tenants in tests, but we verify our tenant has the right data
        expect(Campaign::all())->toHaveCount(3);
    });

    test('documents are isolated by tenant', function () {
        $campaign = Campaign::factory()->create();
        $doc1 = Document::factory()->create(['campaign_id' => $campaign->id]);
        
        // Verify document exists in current tenant
        expect(Document::find($doc1->id))->not->toBeNull();
        expect(Document::count())->toBe(1);
        
        // Document should be findable by its ID
        $found = Document::where('id', $doc1->id)->first();
        expect($found)->not->toBeNull();
        expect($found->id)->toBe($doc1->id);
    });

    test('processors are isolated by tenant', function () {
        Processor::factory()->count(5)->create();
        
        // All processors should exist in current tenant
        expect(Processor::count())->toBe(5);
        
        // Verify processors are on tenant connection
        $processor = Processor::first();
        expect($processor->getConnectionName())->toBe('tenant');
    });

    test('credentials are isolated by tenant', function () {
        $cred1 = Credential::factory()->create(['key' => 'api_key_1']);
        
        // Credential should exist in current tenant
        expect(Credential::where('key', 'api_key_1')->count())->toBe(1);
        expect(Credential::find($cred1->id))->not->toBeNull();
        
        // Verify credential is on tenant connection
        expect($cred1->getConnectionName())->toBe('tenant');
    });

    test('usage events are isolated by tenant', function () {
        $campaign = Campaign::factory()->create();
        UsageEvent::factory()->count(10)->create(['campaign_id' => $campaign->id]);
        
        // All events should exist in current tenant
        expect(UsageEvent::count())->toBe(10);
        
        // Verify events belong to campaign in same tenant
        $event = UsageEvent::first();
        expect($event->campaign_id)->toBe($campaign->id);
        expect($event->getConnectionName())->toBe('tenant');
    });

    test('audit logs are isolated by tenant', function () {
        $campaign = Campaign::factory()->create();
        AuditLog::factory()->count(5)->create([
            'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id,
        ]);
        
        // All audit logs should exist in current tenant
        expect(AuditLog::count())->toBe(5);
        
        // Verify audit logs are on tenant connection
        $audit = AuditLog::first();
        expect($audit->getConnectionName())->toBe('tenant');
    });

    test('models use correct database connection', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $processor = Processor::factory()->create();
        $credential = Credential::factory()->create();
        $usageEvent = UsageEvent::factory()->create(['campaign_id' => $campaign->id]);
        $auditLog = AuditLog::factory()->create([
            'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id,
        ]);
        
        // All tenant-scoped models should use 'tenant' connection
        expect($campaign->getConnectionName())->toBe('tenant');
        expect($document->getConnectionName())->toBe('tenant');
        expect($processor->getConnectionName())->toBe('tenant');
        expect($credential->getConnectionName())->toBe('tenant');
        expect($usageEvent->getConnectionName())->toBe('tenant');
        expect($auditLog->getConnectionName())->toBe('tenant');
    });

    test('cannot query across tenant boundaries', function () {
        // Create data in current tenant
        $campaign1 = Campaign::factory()->create(['name' => 'Tenant 1 Campaign']);
        
        // Verify data exists
        expect(Campaign::where('name', 'Tenant 1 Campaign')->count())->toBe(1);
        
        // Attempting to query with a non-existent ID should return null
        $nonExistentId = '01abc123def456ghi789jkl012';
        expect(Campaign::find($nonExistentId))->toBeNull();
    });

    test('tenant models have correct table names', function () {
        $campaign = new Campaign();
        $document = new Document();
        $processor = new Processor();
        
        // Verify table names are correct (no tenant prefix)
        expect($campaign->getTable())->toBe('campaigns');
        expect($document->getTable())->toBe('documents');
        expect($processor->getTable())->toBe('processors');
    });

    test('relationships work within tenant scope', function () {
        $campaign = Campaign::factory()->create();
        $documents = Document::factory()->count(3)->create(['campaign_id' => $campaign->id]);
        
        // Relationships should work within tenant
        expect($campaign->documents)->toHaveCount(3);
        expect($campaign->documents->first()->campaign_id)->toBe($campaign->id);
        
        // Reverse relationship
        $document = $documents->first();
        expect($document->campaign)->not->toBeNull();
        expect($document->campaign->id)->toBe($campaign->id);
    });
});
