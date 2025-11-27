<?php

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\ProcessorExecution;
use App\Models\Processor;
use App\Models\Tenant;
use App\States\Document\{PendingDocumentState, QueuedDocumentState, ProcessingDocumentState, CompletedDocumentState, FailedDocumentState, CancelledDocumentState};
use App\States\DocumentJob\{PendingJobState, QueuedJobState, RunningJobState, CompletedJobState, FailedJobState, CancelledJobState};
use App\States\ProcessorExecution\{PendingExecutionState, RunningExecutionState, CompletedExecutionState, FailedExecutionState, SkippedExecutionState};
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\ModelStates\Exceptions\TransitionNotAllowed;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create tenant with database using the command
    $tenantName = 'Test-' . Str::random(8);
    $domain = Str::slug($tenantName) . '.test';
    
    Artisan::call('tenant:create', [
        'name' => $tenantName,
        '--domain' => $domain,
    ]);
    
    $this->tenant = Tenant::where('name', $tenantName)->first();
    
    TenantContext::run($this->tenant, function () {
        $this->campaign = Campaign::factory()->create();
    });
});

describe('Document State Machine', function () {
    test('document initializes with pending state', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            expect($document->status)->toBeInstanceOf(PendingDocumentState::class);
            expect($document->status->label())->toBe('Pending');
            expect($document->status->color())->toBe('gray');
        });
    });

    test('document can transition from pending to queued', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            $document->status->transitionTo(QueuedDocumentState::class);

            expect($document->fresh()->status)->toBeInstanceOf(QueuedDocumentState::class);
        });
    });

    test('document can transition through complete lifecycle', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            $document->status->transitionTo(QueuedDocumentState::class);
            expect($document->fresh()->status)->toBeInstanceOf(QueuedDocumentState::class);

            $document->status->transitionTo(ProcessingDocumentState::class);
            expect($document->fresh()->status)->toBeInstanceOf(ProcessingDocumentState::class);

            $document->status->transitionTo(CompletedDocumentState::class);
            expect($document->fresh()->status)->toBeInstanceOf(CompletedDocumentState::class);
            expect($document->fresh()->processed_at)->not->toBeNull();
        });
    });

    test('document can transition from processing to failed', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            $document->status->transitionTo(QueuedDocumentState::class);
            $document->status->transitionTo(ProcessingDocumentState::class);
            $document->status->transitionTo(FailedDocumentState::class);

            expect($document->fresh()->status)->toBeInstanceOf(FailedDocumentState::class);
            expect($document->fresh()->failed_at)->not->toBeNull();
        });
    });

    test('document can be cancelled from any non-final state', function () {
        TenantContext::run($this->tenant, function () {
            $doc1 = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $doc1->status->transitionTo(CancelledDocumentState::class);
            expect($doc1->fresh()->status)->toBeInstanceOf(CancelledDocumentState::class);

            $doc2 = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $doc2->status->transitionTo(QueuedDocumentState::class);
            $doc2->status->transitionTo(CancelledDocumentState::class);
            expect($doc2->fresh()->status)->toBeInstanceOf(CancelledDocumentState::class);

            $doc3 = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $doc3->status->transitionTo(QueuedDocumentState::class);
            $doc3->status->transitionTo(ProcessingDocumentState::class);
            $doc3->status->transitionTo(CancelledDocumentState::class);
            expect($doc3->fresh()->status)->toBeInstanceOf(CancelledDocumentState::class);
        });
    });

    test('document cannot skip states in the pipeline', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            expect(fn() => $document->status->transitionTo(CompletedDocumentState::class))
                ->toThrow(TransitionNotAllowed::class);
        });
    });

    test('document cannot transition backwards', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            $document->status->transitionTo(QueuedDocumentState::class);

            expect(fn() => $document->status->transitionTo(PendingDocumentState::class))
                ->toThrow(TransitionNotAllowed::class);
        });
    });

    test('completed document automatically sets processed_at', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            expect($document->processed_at)->toBeNull();

            $document->status->transitionTo(QueuedDocumentState::class);
            $document->status->transitionTo(ProcessingDocumentState::class);
            $document->status->transitionTo(CompletedDocumentState::class);

            expect($document->fresh()->processed_at)->not->toBeNull();
        });
    });

    test('failed document automatically sets failed_at', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create([
                'campaign_id' => $this->campaign->id,
            ]);

            expect($document->failed_at)->toBeNull();

            $document->status->transitionTo(QueuedDocumentState::class);
            $document->status->transitionTo(ProcessingDocumentState::class);
            $document->status->transitionTo(FailedDocumentState::class);

            expect($document->fresh()->failed_at)->not->toBeNull();
        });
    });
});

describe('DocumentJob State Machine', function () {
    test('job initializes with pending state', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);

            expect($job->status)->toBeInstanceOf(PendingJobState::class);
            expect($job->status->label())->toBe('Pending');
        });
    });

    test('job can transition through complete lifecycle', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);

            $job->status->transitionTo(QueuedJobState::class);
            expect($job->fresh()->status)->toBeInstanceOf(QueuedJobState::class);

            $job->status->transitionTo(RunningJobState::class);
            expect($job->fresh()->status)->toBeInstanceOf(RunningJobState::class);
            expect($job->fresh()->started_at)->not->toBeNull();

            $job->status->transitionTo(CompletedJobState::class);
            expect($job->fresh()->status)->toBeInstanceOf(CompletedJobState::class);
            expect($job->fresh()->completed_at)->not->toBeNull();
        });
    });

    test('job can transition from running to failed', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);

            $job->status->transitionTo(QueuedJobState::class);
            $job->status->transitionTo(RunningJobState::class);
            $job->status->transitionTo(FailedJobState::class);

            expect($job->fresh()->status)->toBeInstanceOf(FailedJobState::class);
            expect($job->fresh()->failed_at)->not->toBeNull();
        });
    });

    test('failed job can retry by transitioning back to queued', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);

            $job->status->transitionTo(QueuedJobState::class);
            $job->status->transitionTo(RunningJobState::class);
            $job->status->transitionTo(FailedJobState::class);

            $job->status->transitionTo(QueuedJobState::class);

            expect($job->fresh()->status)->toBeInstanceOf(QueuedJobState::class);
        });
    });

    test('job can be cancelled from non-final states', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            
            $job1 = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $job1->status->transitionTo(CancelledJobState::class);
            expect($job1->fresh()->status)->toBeInstanceOf(CancelledJobState::class);

            $job2 = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $job2->status->transitionTo(QueuedJobState::class);
            $job2->status->transitionTo(RunningJobState::class);
            $job2->status->transitionTo(CancelledJobState::class);
            expect($job2->fresh()->status)->toBeInstanceOf(CancelledJobState::class);
        });
    });

    test('running job automatically sets started_at', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);

            expect($job->started_at)->toBeNull();

            $job->status->transitionTo(QueuedJobState::class);
            $job->status->transitionTo(RunningJobState::class);

            expect($job->fresh()->started_at)->not->toBeNull();
        });
    });
});

describe('ProcessorExecution State Machine', function () {
    test('execution initializes with pending state', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            
            $execution = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);

            expect($execution->status)->toBeInstanceOf(PendingExecutionState::class);
            expect($execution->status->label())->toBe('Pending');
        });
    });

    test('execution can transition through complete lifecycle', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            
            $execution = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);

            $execution->status->transitionTo(RunningExecutionState::class);
            expect($execution->fresh()->status)->toBeInstanceOf(RunningExecutionState::class);
            expect($execution->fresh()->started_at)->not->toBeNull();

            $execution->status->transitionTo(CompletedExecutionState::class);
            expect($execution->fresh()->status)->toBeInstanceOf(CompletedExecutionState::class);
            expect($execution->fresh()->completed_at)->not->toBeNull();
            expect($execution->fresh()->duration_ms)->toBeGreaterThan(0);
        });
    });

    test('execution can transition from running to failed', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            
            $execution = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);

            $execution->status->transitionTo(RunningExecutionState::class);
            $execution->status->transitionTo(FailedExecutionState::class);

            expect($execution->fresh()->status)->toBeInstanceOf(FailedExecutionState::class);
            expect($execution->fresh()->duration_ms)->toBeGreaterThan(0);
        });
    });

    test('execution can be skipped from pending', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            
            $execution = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);

            $execution->status->transitionTo(SkippedExecutionState::class);

            expect($execution->fresh()->status)->toBeInstanceOf(SkippedExecutionState::class);
        });
    });

    test('execution cannot skip running state', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            
            $execution = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);

            expect(fn() => $execution->status->transitionTo(CompletedExecutionState::class))
                ->toThrow(TransitionNotAllowed::class);
        });
    });

    test('completed execution automatically calculates duration', function () {
        TenantContext::run($this->tenant, function () {
            $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
            $job = DocumentJob::factory()->create([
                'campaign_id' => $this->campaign->id,
                'document_id' => $document->id,
            ]);
            $processor = Processor::factory()->create();
            
            $execution = ProcessorExecution::factory()->create([
                'job_id' => $job->id,
                'processor_id' => $processor->id,
            ]);

            expect($execution->duration_ms)->toBeNull();

            $execution->status->transitionTo(RunningExecutionState::class);
            sleep(1);
            $execution->status->transitionTo(CompletedExecutionState::class);

            expect($execution->fresh()->duration_ms)->toBeGreaterThan(0);
        });
    });
});
