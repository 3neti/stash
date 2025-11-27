<?php

use App\Models\{Campaign, Document, DocumentJob, Processor, ProcessorExecution, Credential, UsageEvent, AuditLog, Tenant};
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

describe('Factory Validation Tests', function () {
    test('campaign factory generates valid campaigns', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();

            expect($campaign)->toBeInstanceOf(Campaign::class);
            expect($campaign->id)->not->toBeNull();
            expect($campaign->name)->not->toBeNull();
            expect($campaign->slug)->not->toBeNull();
            expect($campaign->pipeline_config)->toBeArray();
            expect($campaign->settings)->toBeArray();
        });
    });

    test('campaign factory active state works', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->active()->create();

            expect($campaign->status)->toBe('active');
        });
    });

    test('document factory generates valid documents', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);

            expect($document)->toBeInstanceOf(Document::class);
            expect($document->uuid)->not->toBeNull();
            expect($document->original_filename)->not->toBeNull();
            expect($document->mime_type)->not->toBeNull();
            expect($document->size_bytes)->toBeGreaterThan(0);
            expect($document->metadata)->toBeArray();
        });
    });

    test('document factory state methods work', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            
            $completed = Document::factory()->completed()->create(['campaign_id' => $campaign->id]);
            expect($completed->status->label())->toBe('Completed');
            expect($completed->processed_at)->not->toBeNull();

            $failed = Document::factory()->failed()->create(['campaign_id' => $campaign->id]);
            expect($failed->error_message)->not->toBeNull();
            expect($failed->failed_at)->not->toBeNull();
        });
    });

    test('document job factory generates valid jobs', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $campaign->id,
                'document_id' => $document->id,
            ]);

            expect($job)->toBeInstanceOf(DocumentJob::class);
            expect($job->uuid)->not->toBeNull();
            expect($job->pipeline_instance)->toBeArray();
            expect($job->attempts)->toBeGreaterThanOrEqual(0);
            expect($job->max_attempts)->toBeGreaterThan(0);
        });
    });

    test('processor factory generates valid processors', function () {
        TenantContext::run($this->tenant, function () {
            $processor = Processor::factory()->create();

            expect($processor)->toBeInstanceOf(Processor::class);
            expect($processor->name)->not->toBeNull();
            expect($processor->slug)->not->toBeNull();
            expect($processor->class_name)->not->toBeNull();
            expect($processor->category)->not->toBeNull();
            expect($processor->config_schema)->toBeArray();
        });
    });

    test('processor factory system state works', function () {
        TenantContext::run($this->tenant, function () {
            $processor = Processor::factory()->system()->create();

            expect($processor->is_system)->toBeTrue();
        });
    });

    test('processor execution factory generates valid executions', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            $execution = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);

            expect($execution)->toBeInstanceOf(ProcessorExecution::class);
            expect($execution->input_data)->toBeArray();
            expect($execution->config)->toBeArray();
            expect($execution->tokens_used)->toBeGreaterThanOrEqual(0);
        });
    });

    test('credential factory generates valid credentials', function () {
        TenantContext::run($this->tenant, function () {
            $credential = Credential::factory()->create();

            expect($credential)->toBeInstanceOf(Credential::class);
            expect($credential->key)->not->toBeNull();
            expect($credential->value)->not->toBeNull();
            expect($credential->scope_type)->not->toBeNull();
            expect($credential->provider)->not->toBeNull();
        });
    });

    test('credential factory scope states work', function () {
        TenantContext::run($this->tenant, function () {
            $system = Credential::factory()->system()->create();
            expect($system->scope_type)->toBe('system');
            expect($system->scope_id)->toBeNull();

            $subscriber = Credential::factory()->subscriber()->create();
            expect($subscriber->scope_type)->toBe('subscriber');
        });
    });

    test('usage event factory generates valid events', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $event = UsageEvent::factory()->create(['campaign_id' => $campaign->id]);

            expect($event)->toBeInstanceOf(UsageEvent::class);
            expect($event->event_type)->not->toBeNull();
            expect($event->units)->toBeGreaterThan(0);
            expect($event->cost_credits)->toBeGreaterThan(0);
        });
    });

    test('audit log factory generates valid logs', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $audit = AuditLog::factory()->create([
                'auditable_type' => Campaign::class,
                'auditable_id' => $campaign->id,
            ]);

            expect($audit)->toBeInstanceOf(AuditLog::class);
            expect($audit->event)->not->toBeNull();
            expect($audit->auditable_type)->toBe(Campaign::class);
        });
    });
});

describe('Seeder Validation Tests', function () {
    test('processor seeder creates system processors', function () {
        TenantContext::run($this->tenant, function () {
            $this->artisan('db:seed', ['--class' => 'ProcessorSeeder']);

            expect(Processor::count())->toBeGreaterThan(0);
            expect(Processor::system()->count())->toBeGreaterThan(0);
            
            $ocr = Processor::where('category', 'ocr')->first();
            expect($ocr)->not->toBeNull();
            expect($ocr->config_schema)->toBeArray();
        });
    });

    test('campaign seeder creates campaigns with pipelines', function () {
        TenantContext::run($this->tenant, function () {
            $this->artisan('db:seed', ['--class' => 'ProcessorSeeder']);
            $this->artisan('db:seed', ['--class' => 'CampaignSeeder']);

            expect(Campaign::count())->toBeGreaterThan(0);
            
            $campaign = Campaign::first();
            expect($campaign->pipeline_config)->toBeArray();
            expect($campaign->pipeline_config)->toHaveKey('processors');
            expect($campaign->settings)->toBeArray();
        });
    });

    test('credential seeder creates system credentials', function () {
        TenantContext::run($this->tenant, function () {
            $this->artisan('db:seed', ['--class' => 'CredentialSeeder']);

            expect(Credential::count())->toBeGreaterThan(0);
            expect(Credential::system()->count())->toBeGreaterThan(0);
            
            $openai = Credential::where('key', 'openai_api_key')->first();
            expect($openai)->not->toBeNull();
            expect($openai->provider)->toBe('openai');
        });
    });

    test('demo data seeder creates complete workflow', function () {
        TenantContext::run($this->tenant, function () {
            $this->artisan('db:seed', ['--class' => 'ProcessorSeeder']);
            $this->artisan('db:seed', ['--class' => 'CampaignSeeder']);
            $this->artisan('db:seed', ['--class' => 'DemoDataSeeder']);

            expect(Document::count())->toBeGreaterThan(0);
            expect(DocumentJob::count())->toBeGreaterThan(0);
            expect(ProcessorExecution::count())->toBeGreaterThan(0);
            expect(UsageEvent::count())->toBeGreaterThan(0);
            expect(AuditLog::count())->toBeGreaterThan(0);
        });
    });

    test('seeded data has proper relationships', function () {
        TenantContext::run($this->tenant, function () {
            $this->artisan('db:seed', ['--class' => 'ProcessorSeeder']);
            $this->artisan('db:seed', ['--class' => 'CampaignSeeder']);
            $this->artisan('db:seed', ['--class' => 'DemoDataSeeder']);

            $document = Document::first();
            expect($document->campaign)->not->toBeNull();
            
            $job = DocumentJob::first();
            expect($job->campaign)->not->toBeNull();
            expect($job->document)->not->toBeNull();
            
            $execution = ProcessorExecution::first();
            expect($execution->documentJob)->not->toBeNull();
            expect($execution->processor)->not->toBeNull();
        });
    });

    test('seeded data has variety in statuses', function () {
        TenantContext::run($this->tenant, function () {
            $this->artisan('db:seed', ['--class' => 'ProcessorSeeder']);
            $this->artisan('db:seed', ['--class' => 'CampaignSeeder']);
            $this->artisan('db:seed', ['--class' => 'DemoDataSeeder']);

            $statuses = Document::pluck('status')->unique();
            expect($statuses->count())->toBeGreaterThan(1);
        });
    });
});
