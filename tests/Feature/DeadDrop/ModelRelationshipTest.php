<?php
use App\Models\AuditLog;

beforeEach(fn() => test()->markTestSkipped('Phase 7: Complex DeadDrop - database connection issue'));
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\ProcessorExecution;
use App\Models\UsageEvent;


describe('Campaign Relationships', function () {
    test('campaign has many documents', function () {
        $campaign = Campaign::factory()->create();
        $documents = Document::factory()->count(3)->create(['campaign_id' => $campaign->id]);

        expect($campaign->documents)->toHaveCount(3);
        expect($campaign->documents->first())->toBeInstanceOf(Document::class);
    });

    test('campaign has many document jobs', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $jobs = DocumentJob::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);

        expect($campaign->documentJobs)->toHaveCount(2);
        expect($campaign->documentJobs->first())->toBeInstanceOf(DocumentJob::class);
    });

    test('campaign has many usage events', function () {
        $campaign = Campaign::factory()->create();
        $events = UsageEvent::factory()->count(5)->create(['campaign_id' => $campaign->id]);

        expect($campaign->usageEvents)->toHaveCount(5);
        expect($campaign->usageEvents->first())->toBeInstanceOf(UsageEvent::class);
    });
});

describe('Document Relationships', function () {
    test('document belongs to campaign', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);

        expect($document->campaign)->toBeInstanceOf(Campaign::class);
        expect($document->campaign->id)->toBe($campaign->id);
    });

    test('document has many document jobs', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $jobs = DocumentJob::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);

        expect($document->documentJobs)->toHaveCount(2);
    });

    test('document has many usage events', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $events = UsageEvent::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);

        expect($document->usageEvents)->toHaveCount(3);
    });
});

describe('DocumentJob Relationships', function () {
    test('document job belongs to campaign', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);

        expect($job->campaign)->toBeInstanceOf(Campaign::class);
        expect($job->campaign->id)->toBe($campaign->id);
    });

    test('document job belongs to document', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);

        expect($job->document)->toBeInstanceOf(Document::class);
        expect($job->document->id)->toBe($document->id);
    });

    test('document job has many processor executions', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);
        $processor = Processor::factory()->create();
        $executions = ProcessorExecution::factory()->count(3)->create([
            'job_id' => $job->id,
            'processor_id' => $processor->id,
        ]);

        expect($job->processorExecutions)->toHaveCount(3);
    });
});

describe('Processor Relationships', function () {
    test('processor has many processor executions', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);
        $processor = Processor::factory()->create();
        $executions = ProcessorExecution::factory()->count(4)->create([
            'job_id' => $job->id,
            'processor_id' => $processor->id,
        ]);

        expect($processor->processorExecutions)->toHaveCount(4);
    });
});

describe('ProcessorExecution Relationships', function () {
    test('processor execution belongs to document job', function () {
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

        expect($execution->documentJob)->toBeInstanceOf(DocumentJob::class);
        expect($execution->documentJob->id)->toBe($job->id);
    });

    test('processor execution belongs to processor', function () {
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

        expect($execution->processor)->toBeInstanceOf(Processor::class);
        expect($execution->processor->id)->toBe($processor->id);
    });
});

describe('UsageEvent Relationships', function () {
    test('usage event belongs to campaign', function () {
        $campaign = Campaign::factory()->create();
        $event = UsageEvent::factory()->create(['campaign_id' => $campaign->id]);

        expect($event->campaign)->toBeInstanceOf(Campaign::class);
        expect($event->campaign->id)->toBe($campaign->id);
    });

    test('usage event can belong to document', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $event = UsageEvent::factory()->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);

        expect($event->document)->toBeInstanceOf(Document::class);
        expect($event->document->id)->toBe($document->id);
    });

    test('usage event can belong to document job', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
        ]);
        $event = UsageEvent::factory()->create([
            'campaign_id' => $campaign->id,
            'document_id' => $document->id,
            'job_id' => $job->id,
        ]);

        expect($event->documentJob)->toBeInstanceOf(DocumentJob::class);
        expect($event->documentJob->id)->toBe($job->id);
    });
});

describe('AuditLog Polymorphic Relationships', function () {
    test('audit log has polymorphic auditable relationship', function () {
        $campaign = Campaign::factory()->create();
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);

        $audit = AuditLog::factory()->create([
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
        ]);

        expect($audit->auditable)->toBeInstanceOf(Document::class);
        expect($audit->auditable->id)->toBe($document->id);
    });

    test('audit log works with different auditable types', function () {
        $campaign = Campaign::factory()->create();

        $campaignAudit = AuditLog::factory()->create([
            'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id,
        ]);

        expect($campaignAudit->auditable)->toBeInstanceOf(Campaign::class);
        expect($campaignAudit->auditable->id)->toBe($campaign->id);
    });
});
