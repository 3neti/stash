<?php

use App\Models\Campaign;
use App\Models\Document;
use App\Models\User;
use App\States\Document\CompletedDocumentState;
use App\States\Document\FailedDocumentState;
use App\States\Document\PendingDocumentState;

describe('Document Model - Direct Creation', function () {
    test('can create document with minimal attributes', function () {
        $campaign = Campaign::factory()->create();

        $document = Document::create([
            'campaign_id' => $campaign->id,
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'storage_path' => 'documents/test.pdf',
            'hash' => hash('sha256', 'test'),
        ]);

        expect($document)->toBeInstanceOf(Document::class)
            ->and($document->id)->not->toBeNull()
            ->and($document->original_filename)->toBe('test.pdf');
    });

    test('document uses ULID for primary key', function () {
        $campaign = Campaign::factory()->create();

        $document = Document::create([
            'campaign_id' => $campaign->id,
            'original_filename' => 'ulid-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'storage_path' => 'documents/ulid-test.pdf',
            'hash' => hash('sha256', 'ulid-test'),
        ]);

        // ULIDs are 26 characters, base32 encoded (case-insensitive)
        expect($document->id)
            ->toBeString()
            ->toHaveLength(26)
            ->toMatch('/^[0-9a-hjkmnp-tv-zA-HJKMNP-TV-Z]{26}$/');
    });

    test('document has correct default values', function () {
        $campaign = Campaign::factory()->create();

        $document = Document::create([
            'campaign_id' => $campaign->id,
            'original_filename' => 'defaults-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'storage_path' => 'documents/defaults-test.pdf',
            'hash' => hash('sha256', 'defaults-test'),
        ]);

        expect($document->state)->toBeInstanceOf(PendingDocumentState::class)
            ->and($document->storage_disk)->toBe('s3')
            ->and($document->retry_count)->toBe(0);
    });
});

describe('Document Model - Factory Creation', function () {
    test('can create document using factory', function () {
        $document = Document::factory()->create();

        expect($document)->toBeInstanceOf(Document::class)
            ->and($document->id)->not->toBeNull()
            ->and($document->campaign_id)->not->toBeNull()
            ->and($document->original_filename)->not->toBeNull();
    });

    test('can create multiple documents with factory', function () {
        $documents = Document::factory()->count(5)->create();

        expect($documents)->toHaveCount(5);

        // Verify all have unique IDs
        $ids = $documents->pluck('id')->unique();
        expect($ids)->toHaveCount(5);
    });
});

describe('Document Model - Relationships', function () {
    test('document belongs to campaign', function () {
        $campaign = Campaign::factory()->create(['name' => 'Test Campaign']);
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);

        expect($document->campaign)->toBeInstanceOf(Campaign::class)
            ->and($document->campaign->name)->toBe('Test Campaign');
    });

    test('document belongs to user when user_id is set', function () {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);

        expect($document->user)->toBeInstanceOf(User::class)
            ->and($document->user->id)->toBe($user->id);
    });

    test('can assign user to document after creation', function () {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => null]);

        expect($document->user)->toBeNull();

        // For cross-database relationships, direct assignment works
        $document->update(['user_id' => $user->id]);

        // Explicitly load the relationship
        $document->load('user');

        expect($document->user)->toBeInstanceOf(User::class)
            ->and($document->user->id)->toBe($user->id)
            ->and($document->user_id)->toBe($user->id);
    });

    test('document can have no user', function () {
        $document = Document::factory()->create(['user_id' => null]);

        expect($document->user)->toBeNull();
    });
});

describe('Document Model - State Management', function () {
    test('document starts in pending state by default', function () {
        $document = Document::factory()->create();

        expect($document->state)->toBeInstanceOf(PendingDocumentState::class);
    });

    test('can transition document to completed state', function () {
        $document = Document::factory()->create();

        $document->state->transitionTo(CompletedDocumentState::class);
        $document->refresh();

        expect($document->state)->toBeInstanceOf(CompletedDocumentState::class);
    });

    test('can transition document to failed state', function () {
        $document = Document::factory()->create();

        $document->state->transitionTo(FailedDocumentState::class);
        $document->refresh();

        expect($document->state)->toBeInstanceOf(FailedDocumentState::class);
    });

    test('markCompleted sets state and processed_at', function () {
        $document = Document::factory()->create();

        expect($document->processed_at)->toBeNull();

        $document->markCompleted();
        $document->refresh();

        expect($document->state)->toBeInstanceOf(CompletedDocumentState::class)
            ->and($document->processed_at)->not->toBeNull()
            ->and($document->processed_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });

    test('markFailed sets state, error message and failed_at', function () {
        $document = Document::factory()->create();
        $errorMessage = 'Processing failed due to timeout';

        expect($document->failed_at)->toBeNull()
            ->and($document->error_message)->toBeNull();

        $document->markFailed($errorMessage);
        $document->refresh();

        expect($document->state)->toBeInstanceOf(FailedDocumentState::class)
            ->and($document->error_message)->toBe($errorMessage)
            ->and($document->failed_at)->not->toBeNull()
            ->and($document->failed_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });
});

describe('Document Model - Methods', function () {
    test('isCompleted returns true for completed documents', function () {
        $completedDoc = Document::factory()->create();
        $completedDoc->markCompleted();

        $pendingDoc = Document::factory()->create();

        expect($completedDoc->isCompleted())->toBeTrue()
            ->and($pendingDoc->isCompleted())->toBeFalse();
    });

    test('isFailed returns true for failed documents', function () {
        $failedDoc = Document::factory()->create();
        $failedDoc->markFailed('Test error');

        $pendingDoc = Document::factory()->create();

        expect($failedDoc->isFailed())->toBeTrue()
            ->and($pendingDoc->isFailed())->toBeFalse();
    });

    test('incrementRetries increments retry count', function () {
        $document = Document::factory()->create();

        expect($document->retry_count)->toBe(0);

        $document->incrementRetries();
        $document->refresh();

        expect($document->retry_count)->toBe(1);

        $document->incrementRetries();
        $document->refresh();

        expect($document->retry_count)->toBe(2);
    });

    test('formatted_size attribute returns human readable size', function () {
        $docB = Document::factory()->create(['size_bytes' => 500]);
        $docKB = Document::factory()->create(['size_bytes' => 1024 * 50]); // 50 KB
        $docMB = Document::factory()->create(['size_bytes' => 1024 * 1024 * 2]); // 2 MB

        expect($docB->formatted_size)->toBe('500 B')
            ->and($docKB->formatted_size)->toBe('50 KB')
            ->and($docMB->formatted_size)->toBe('2 MB');
    });

    test('addProcessingHistory appends to processing history', function () {
        $document = Document::factory()->create(['processing_history' => []]);

        $document->addProcessingHistory('ocr', ['confidence' => 0.95]);
        $document->refresh();

        expect($document->processing_history)->toHaveCount(1)
            ->and($document->processing_history[0]['stage'])->toBe('ocr')
            ->and($document->processing_history[0]['data']['confidence'])->toBe(0.95);

        $document->addProcessingHistory('classification', ['category' => 'invoice']);
        $document->refresh();

        expect($document->processing_history)->toHaveCount(2);
    });
});

describe('Document Model - Scopes', function () {
    test('pending scope returns only pending documents', function () {
        Document::factory()->create(); // pending by default
        Document::factory()->create(); // pending by default
        $completed = Document::factory()->create();
        $completed->markCompleted();

        $pending = Document::pending()->get();

        expect($pending)->toHaveCount(2);
        expect($pending->every(fn ($d) => $d->state instanceof PendingDocumentState))->toBeTrue();
    });

    test('completed scope returns only completed documents', function () {
        Document::factory()->create(); // pending
        $completed1 = Document::factory()->create();
        $completed1->markCompleted();
        $completed2 = Document::factory()->create();
        $completed2->markCompleted();

        $completed = Document::completed()->get();

        expect($completed)->toHaveCount(2);
        expect($completed->every(fn ($d) => $d->state instanceof CompletedDocumentState))->toBeTrue();
    });

    test('failed scope returns only failed documents', function () {
        Document::factory()->create(); // pending
        $failed1 = Document::factory()->create();
        $failed1->markFailed('Error 1');
        $failed2 = Document::factory()->create();
        $failed2->markFailed('Error 2');

        $failed = Document::failed()->get();

        expect($failed)->toHaveCount(2);
        expect($failed->every(fn ($d) => $d->state instanceof FailedDocumentState))->toBeTrue();
    });
});

describe('Document Model - Soft Deletes', function () {
    test('documents can be soft deleted', function () {
        $document = Document::factory()->create();
        $id = $document->id;

        $document->delete();

        expect(Document::find($id))->toBeNull()
            ->and(Document::withTrashed()->find($id))->not->toBeNull();
    });

    test('soft deleted documents can be restored', function () {
        $document = Document::factory()->create();
        $id = $document->id;

        $document->delete();
        $deleted = Document::withTrashed()->find($id);
        $deleted->restore();

        expect(Document::find($id))->not->toBeNull();
    });
});

describe('Document Model - Casts', function () {
    test('metadata is cast to array', function () {
        $metadata = ['extracted_text' => 'Sample text', 'page_count' => 5];
        $document = Document::factory()->create(['metadata' => $metadata]);

        $fresh = Document::find($document->id);

        expect($fresh->metadata)->toBeArray()
            ->and($fresh->metadata['extracted_text'])->toBe('Sample text')
            ->and($fresh->metadata['page_count'])->toBe(5);
    });

    test('processing_history is cast to array', function () {
        $history = [['stage' => 'upload', 'timestamp' => now()->toIso8601String()]];
        $document = Document::factory()->create(['processing_history' => $history]);

        $fresh = Document::find($document->id);

        expect($fresh->processing_history)->toBeArray()
            ->and($fresh->processing_history[0]['stage'])->toBe('upload');
    });

    test('timestamps are cast to Carbon instances', function () {
        $document = Document::factory()->create([
            'processed_at' => now(),
            'failed_at' => now(),
        ]);

        expect($document->processed_at)->toBeInstanceOf(\Carbon\Carbon::class)
            ->and($document->failed_at)->toBeInstanceOf(\Carbon\Carbon::class);
    });
});
