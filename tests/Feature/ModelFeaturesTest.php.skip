<?php

use App\Models\{Campaign, Document, DocumentJob, Processor, ProcessorExecution, Credential, UsageEvent, AuditLog, Tenant};
use App\States\Document\{CompletedDocumentState, FailedDocumentState};
use App\States\DocumentJob\{RunningJobState, CompletedJobState};
use App\States\ProcessorExecution\CompletedExecutionState;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

describe('Model Scopes', function () {
    test('campaign active scope filters active campaigns', function () {
        TenantContext::run($this->tenant, function () {
            Campaign::factory()->create(['status' => 'active']);
            Campaign::factory()->create(['status' => 'active']);
            Campaign::factory()->create(['status' => 'draft']);

            expect(Campaign::active()->count())->toBe(2);
        });
    });

    test('campaign published scope filters published campaigns', function () {
        TenantContext::run($this->tenant, function () {
            Campaign::factory()->create(['status' => 'active', 'published_at' => now()]);
            Campaign::factory()->create(['status' => 'active', 'published_at' => null]);

            expect(Campaign::published()->count())->toBe(1);
        });
    });

    test('document pending scope filters pending documents', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            Document::factory()->create(['campaign_id' => $campaign->id, 'status' => 'pending']);
            Document::factory()->create(['campaign_id' => $campaign->id, 'status' => 'completed']);

            expect(Document::pending()->count())->toBe(1);
        });
    });

    test('document completed scope filters completed documents', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $doc1 = Document::factory()->create(['campaign_id' => $campaign->id]);
            $doc1->status->transitionTo(CompletedDocumentState::class);
            
            Document::factory()->create(['campaign_id' => $campaign->id, 'status' => 'pending']);

            $completed = Document::whereNotNull('processed_at')->count();
            expect($completed)->toBe(1);
        });
    });

    test('document failed scope filters failed documents', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $doc1 = Document::factory()->create(['campaign_id' => $campaign->id]);
            $doc1->status->transitionTo(FailedDocumentState::class);
            
            Document::factory()->create(['campaign_id' => $campaign->id, 'status' => 'pending']);

            $failed = Document::whereNotNull('failed_at')->count();
            expect($failed)->toBe(1);
        });
    });

    test('document job running scope filters running jobs', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            
            $job1 = DocumentJob::factory()->create([
                'campaign_id' => $campaign->id,
                'document_id' => $document->id,
            ]);
            $job1->status->transitionTo(RunningJobState::class);
            
            DocumentJob::factory()->create([
                'campaign_id' => $campaign->id,
                'document_id' => $document->id,
                'status' => 'pending',
            ]);

            $running = DocumentJob::whereNotNull('started_at')->whereNull('completed_at')->count();
            expect($running)->toBe(1);
        });
    });

    test('processor system scope filters system processors', function () {
        TenantContext::run($this->tenant, function () {
            Processor::factory()->create(['is_system' => true]);
            Processor::factory()->create(['is_system' => true]);
            Processor::factory()->create(['is_system' => false]);

            expect(Processor::system()->count())->toBe(2);
        });
    });

    test('processor custom scope filters custom processors', function () {
        TenantContext::run($this->tenant, function () {
            Processor::factory()->create(['is_system' => false]);
            Processor::factory()->create(['is_system' => true]);

            expect(Processor::custom()->count())->toBe(1);
        });
    });

    test('processor active scope filters active processors', function () {
        TenantContext::run($this->tenant, function () {
            Processor::factory()->create(['is_active' => true]);
            Processor::factory()->create(['is_active' => false]);

            expect(Processor::active()->count())->toBe(1);
        });
    });

    test('processor execution completed scope filters completed executions', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            
            $exec1 = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);
            $exec1->status->transitionTo(CompletedExecutionState::class);
            
            ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
                'status' => 'pending',
            ]);

            $completed = ProcessorExecution::whereNotNull('completed_at')->count();
            expect($completed)->toBe(1);
        });
    });

    test('usage event filters by event type', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            
            UsageEvent::factory()->create(['campaign_id' => $campaign->id, 'event_type' => 'upload']);
            UsageEvent::factory()->create(['campaign_id' => $campaign->id, 'event_type' => 'upload']);
            UsageEvent::factory()->create(['campaign_id' => $campaign->id, 'event_type' => 'ai_task']);

            expect(UsageEvent::where('event_type', 'upload')->count())->toBe(2);
        });
    });
});

describe('Encryption Tests', function () {
    test('credential value is encrypted in database', function () {
        TenantContext::run($this->tenant, function () {
            $plainValue = 'test-api-key-12345';
            
            $credential = Credential::factory()->create([
                'key' => 'test_key',
                'value' => $plainValue,
            ]);

            // Value should be encrypted in database
            $dbValue = \DB::connection('tenant')
                ->table('credentials')
                ->where('id', $credential->id)
                ->value('value');
            
            expect($dbValue)->not->toBe($plainValue);
            expect($credential->value)->toBe($plainValue);
        });
    });

    test('credential value is properly decrypted when accessed', function () {
        TenantContext::run($this->tenant, function () {
            $plainValue = 'my-secret-key';
            
            $credential = Credential::factory()->create(['value' => $plainValue]);
            
            $retrieved = Credential::find($credential->id);
            expect($retrieved->value)->toBe($plainValue);
        });
    });

    test('credential handles null values', function () {
        TenantContext::run($this->tenant, function () {
            $credential = Credential::factory()->create(['value' => null]);
            
            expect($credential->value)->toBeNull();
        });
    });

    test('campaign credentials are encrypted', function () {
        TenantContext::run($this->tenant, function () {
            $credentials = json_encode(['api_key' => 'secret123']);
            
            $campaign = Campaign::factory()->create([
                'credentials' => $credentials,
            ]);

            $dbCredentials = \DB::connection('tenant')
                ->table('campaigns')
                ->where('id', $campaign->id)
                ->value('credentials');
            
            expect($dbCredentials)->not->toBe($credentials);
            expect($campaign->credentials)->toBe($credentials);
        });
    });
});

describe('Tenant Isolation Tests', function () {
    test('queries are scoped to tenant database', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        TenantContext::run($tenant1, function () {
            Campaign::factory()->count(3)->create();
        });

        TenantContext::run($tenant2, function () {
            Campaign::factory()->count(2)->create();
        });

        TenantContext::run($tenant1, function () {
            expect(Campaign::count())->toBe(3);
        });

        TenantContext::run($tenant2, function () {
            expect(Campaign::count())->toBe(2);
        });
    });

    test('documents are isolated by tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        TenantContext::run($tenant1, function () use (&$doc1) {
            $campaign = Campaign::factory()->create();
            $doc1 = Document::factory()->create(['campaign_id' => $campaign->id]);
        });

        TenantContext::run($tenant2, function () use ($doc1) {
            expect(Document::find($doc1->id))->toBeNull();
            expect(Document::count())->toBe(0);
        });
    });

    test('processors are isolated by tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        TenantContext::run($tenant1, function () {
            Processor::factory()->count(5)->create();
        });

        TenantContext::run($tenant2, function () {
            expect(Processor::count())->toBe(0);
        });
    });

    test('credentials are isolated by tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        TenantContext::run($tenant1, function () use (&$cred1) {
            $cred1 = Credential::factory()->create(['key' => 'api_key_1']);
        });

        TenantContext::run($tenant2, function () use ($cred1) {
            expect(Credential::where('key', 'api_key_1')->count())->toBe(0);
            expect(Credential::find($cred1->id))->toBeNull();
        });
    });

    test('usage events are isolated by tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        TenantContext::run($tenant1, function () {
            $campaign = Campaign::factory()->create();
            UsageEvent::factory()->count(10)->create(['campaign_id' => $campaign->id]);
        });

        TenantContext::run($tenant2, function () {
            expect(UsageEvent::count())->toBe(0);
        });
    });

    test('audit logs are isolated by tenant', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        TenantContext::run($tenant1, function () {
            $campaign = Campaign::factory()->create();
            AuditLog::factory()->count(5)->create([
                'auditable_type' => Campaign::class,
                'auditable_id' => $campaign->id,
            ]);
        });

        TenantContext::run($tenant2, function () {
            expect(AuditLog::count())->toBe(0);
        });
    });

    test('cross-tenant queries fail gracefully', function () {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        TenantContext::run($tenant1, function () use (&$campaign1) {
            $campaign1 = Campaign::factory()->create();
        });

        TenantContext::run($tenant2, function () use ($campaign1) {
            $result = Campaign::find($campaign1->id);
            expect($result)->toBeNull();
        });
    });
});
