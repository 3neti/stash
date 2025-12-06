<?php

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\ProcessorExecution;
use App\States\Document\CancelledDocumentState;
use App\States\Document\CompletedDocumentState;
use App\States\Document\FailedDocumentState;
use App\States\Document\PendingDocumentState;
use App\States\Document\ProcessingDocumentState;
use App\States\Document\QueuedDocumentState;
use App\States\DocumentJob\CancelledJobState;
use App\States\DocumentJob\CompletedJobState;
use App\States\DocumentJob\FailedJobState;
use App\States\DocumentJob\PendingJobState;
use App\States\DocumentJob\QueuedJobState;
use App\States\DocumentJob\RunningJobState;
use App\States\ProcessorExecution\CompletedExecutionState;
use App\States\ProcessorExecution\FailedExecutionState;
use App\States\ProcessorExecution\PendingExecutionState;
use App\States\ProcessorExecution\RunningExecutionState;
use App\States\ProcessorExecution\SkippedExecutionState;

uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class);

beforeEach(function () {
    $this->campaign = Campaign::factory()->create();
});

describe('Document State Machine', function () {
    test('document initializes with pending state', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        expect($document->state)->toBeInstanceOf(PendingDocumentState::class);
        expect($document->state->label())->toBe('Pending');
        expect($document->state->color())->toBe('gray');
    });

    test('document can transition from pending to queued', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        $document->state->transitionTo(QueuedDocumentState::class);

        expect($document->fresh()->state)->toBeInstanceOf(QueuedDocumentState::class);
    });

    test('document can transition through complete lifecycle', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        $document->state->transitionTo(QueuedDocumentState::class);
        expect($document->fresh()->state)->toBeInstanceOf(QueuedDocumentState::class);

        $document->state->transitionTo(ProcessingDocumentState::class);
        expect($document->fresh()->state)->toBeInstanceOf(ProcessingDocumentState::class);

        $document->state->transitionTo(CompletedDocumentState::class);
        expect($document->fresh()->state)->toBeInstanceOf(CompletedDocumentState::class);
        expect($document->fresh()->processed_at)->not->toBeNull();
    });

    test('document can transition from processing to failed', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        $document->state->transitionTo(QueuedDocumentState::class);
        $document->state->transitionTo(ProcessingDocumentState::class);
        $document->state->transitionTo(FailedDocumentState::class);

        expect($document->fresh()->state)->toBeInstanceOf(FailedDocumentState::class);
        expect($document->fresh()->failed_at)->not->toBeNull();
    });

    test('document can be cancelled from any non-final state', function () {
        $doc1 = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $doc1->state->transitionTo(CancelledDocumentState::class);
        expect($doc1->fresh()->state)->toBeInstanceOf(CancelledDocumentState::class);

        $doc2 = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $doc2->state->transitionTo(QueuedDocumentState::class);
        $doc2->state->transitionTo(CancelledDocumentState::class);
        expect($doc2->fresh()->state)->toBeInstanceOf(CancelledDocumentState::class);

        $doc3 = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $doc3->state->transitionTo(QueuedDocumentState::class);
        $doc3->state->transitionTo(ProcessingDocumentState::class);
        $doc3->state->transitionTo(CancelledDocumentState::class);
        expect($doc3->fresh()->state)->toBeInstanceOf(CancelledDocumentState::class);
    });

    test('document can directly transition to completed', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        $document->state->transitionTo(CompletedDocumentState::class);
        expect($document->fresh()->state)->toBeInstanceOf(CompletedDocumentState::class);
        expect($document->fresh()->processed_at)->not->toBeNull();
    });

    test('document cannot transition backwards to pending', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        $document->state->transitionTo(QueuedDocumentState::class);

        // TransitionNotFound is thrown when transition not registered
        expect(fn () => $document->state->transitionTo(PendingDocumentState::class))
            ->toThrow(Exception::class);
    });

    test('completed document automatically sets processed_at', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        expect($document->processed_at)->toBeNull();

        $document->state->transitionTo(QueuedDocumentState::class);
        $document->state->transitionTo(ProcessingDocumentState::class);
        $document->state->transitionTo(CompletedDocumentState::class);

        expect($document->fresh()->processed_at)->not->toBeNull();
    });

    test('failed document automatically sets failed_at', function () {
        $document = Document::factory()->create([
            'campaign_id' => $this->campaign->id,
        ]);

        expect($document->failed_at)->toBeNull();

        $document->state->transitionTo(QueuedDocumentState::class);
        $document->state->transitionTo(ProcessingDocumentState::class);
        $document->state->transitionTo(FailedDocumentState::class);

        expect($document->fresh()->failed_at)->not->toBeNull();
    });
});

describe('DocumentJob State Machine', function () {
    test('job initializes with pending state', function () {
        $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $document->id,
        ]);

        expect($job->state)->toBeInstanceOf(PendingJobState::class);
        expect($job->state->label())->toBe('Pending');
    });

    test('job can transition through complete lifecycle', function () {
        $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $document->id,
        ]);

        $job->state->transitionTo(QueuedJobState::class);
        expect($job->fresh()->state)->toBeInstanceOf(QueuedJobState::class);

        $job->state->transitionTo(RunningJobState::class);
        expect($job->fresh()->state)->toBeInstanceOf(RunningJobState::class);
        expect($job->fresh()->started_at)->not->toBeNull();

        $job->state->transitionTo(CompletedJobState::class);
        expect($job->fresh()->state)->toBeInstanceOf(CompletedJobState::class);
        expect($job->fresh()->completed_at)->not->toBeNull();
    });

    test('job can transition from running to failed', function () {
        $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $document->id,
        ]);

        $job->state->transitionTo(QueuedJobState::class);
        $job->state->transitionTo(RunningJobState::class);
        $job->state->transitionTo(FailedJobState::class);

        expect($job->fresh()->state)->toBeInstanceOf(FailedJobState::class);
        expect($job->fresh()->failed_at)->not->toBeNull();
    });

    test('failed job can retry by transitioning back to queued', function () {
        $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $document->id,
        ]);

        $job->state->transitionTo(QueuedJobState::class);
        $job->state->transitionTo(RunningJobState::class);
        $job->state->transitionTo(FailedJobState::class);

        $job->state->transitionTo(QueuedJobState::class);

        expect($job->fresh()->state)->toBeInstanceOf(QueuedJobState::class);
    });

    test('job can be cancelled from non-final states', function () {
        $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);

        $job1 = DocumentJob::factory()->create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $document->id,
        ]);
        $job1->state->transitionTo(CancelledJobState::class);
        expect($job1->fresh()->state)->toBeInstanceOf(CancelledJobState::class);

        $job2 = DocumentJob::factory()->create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $document->id,
        ]);
        $job2->state->transitionTo(QueuedJobState::class);
        $job2->state->transitionTo(RunningJobState::class);
        $job2->state->transitionTo(CancelledJobState::class);
        expect($job2->fresh()->state)->toBeInstanceOf(CancelledJobState::class);
    });

    test('running job automatically sets started_at', function () {
        $document = Document::factory()->create(['campaign_id' => $this->campaign->id]);
        $job = DocumentJob::factory()->create([
            'campaign_id' => $this->campaign->id,
            'document_id' => $document->id,
        ]);

        expect($job->started_at)->toBeNull();

        $job->state->transitionTo(QueuedJobState::class);
        $job->state->transitionTo(RunningJobState::class);

        expect($job->fresh()->started_at)->not->toBeNull();
    });
});

describe('ProcessorExecution State Machine', function () {
    test('execution initializes with pending state', function () {
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

        expect($execution->state)->toBeInstanceOf(PendingExecutionState::class);
        expect($execution->state->label())->toBe('Pending');
    });

    test('execution can transition through complete lifecycle', function () {
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

        $execution->state->transitionTo(RunningExecutionState::class);
        expect($execution->fresh()->state)->toBeInstanceOf(RunningExecutionState::class);
        expect($execution->fresh()->started_at)->not->toBeNull();

        $execution->state->transitionTo(CompletedExecutionState::class);
        expect($execution->fresh()->state)->toBeInstanceOf(CompletedExecutionState::class);
        expect($execution->fresh()->completed_at)->not->toBeNull();
        expect($execution->fresh()->duration_ms)->toBeGreaterThan(0);
    });

    test('execution can transition from running to failed', function () {
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

        $execution->state->transitionTo(RunningExecutionState::class);
        $execution->state->transitionTo(FailedExecutionState::class);

        expect($execution->fresh()->state)->toBeInstanceOf(FailedExecutionState::class);
        expect($execution->fresh()->duration_ms)->toBeGreaterThan(0);
    });

    test('execution can be skipped from pending', function () {
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

        $execution->state->transitionTo(SkippedExecutionState::class);

        expect($execution->fresh()->state)->toBeInstanceOf(SkippedExecutionState::class);
    });

    test('execution cannot skip running state', function () {
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

        // TransitionNotFound is thrown when transition not registered
        expect(fn () => $execution->state->transitionTo(CompletedExecutionState::class))
            ->toThrow(Exception::class);
    });

    test('completed execution automatically calculates duration', function () {
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

        $execution->state->transitionTo(RunningExecutionState::class);
        sleep(1);
        $execution->state->transitionTo(CompletedExecutionState::class);

        expect($execution->fresh()->duration_ms)->toBeGreaterThan(0);
    });
});
