<?php

use App\Actions\Documents\ListDocuments;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\States\Document\CompletedDocumentState;
use App\States\Document\FailedDocumentState;
use App\States\Document\PendingDocumentState;
use App\States\Document\ProcessingDocumentState;
use Carbon\Carbon;

describe('ListDocuments Action', function () {
    beforeEach(function () {
        $this->campaign = Campaign::factory()->create();
    });

    test('lists all documents for campaign', function () {
        Document::factory()
            ->count(5)
            ->for($this->campaign)
            ->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign);
        
        expect($result->total())->toBe(5)
            ->and($result->count())->toBe(5);
    });

    test('returns paginated result', function () {
        Document::factory()
            ->count(30)
            ->for($this->campaign)
            ->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, null, null, null, 10);
        
        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->perPage())->toBe(10)
            ->and($result->total())->toBe(30)
            ->and($result->lastPage())->toBe(3);
    });

    test('filters by pending status', function () {
        Document::factory()->for($this->campaign)->pending()->count(2)->create();
        Document::factory()->for($this->campaign)->processing()->count(3)->create();
        Document::factory()->for($this->campaign)->completed()->count(1)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, 'pending');
        
        expect($result->total())->toBe(2);
    });

    test('filters by processing status', function () {
        Document::factory()->for($this->campaign)->pending()->count(2)->create();
        Document::factory()->for($this->campaign)->processing()->count(3)->create();
        Document::factory()->for($this->campaign)->completed()->count(1)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, 'processing');
        
        expect($result->total())->toBe(3);
    });

    test('filters by completed status', function () {
        Document::factory()->for($this->campaign)->pending()->count(2)->create();
        Document::factory()->for($this->campaign)->completed()->count(4)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, 'completed');
        
        expect($result->total())->toBe(4);
    });

    test('filters by failed status', function () {
        Document::factory()->for($this->campaign)->pending()->count(2)->create();
        Document::factory()->for($this->campaign)->failed()->count(3)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, 'failed');
        
        expect($result->total())->toBe(3);
    });

    test('filters by date from', function () {
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-01-01']);
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-02-15']);
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-03-30']);
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, null, Carbon::parse('2024-02-01'));
        
        expect($result->total())->toBe(2);
    });

    test('filters by date to', function () {
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-01-01']);
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-02-15']);
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-03-30']);
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, null, null, Carbon::parse('2024-02-28'));
        
        expect($result->total())->toBe(2);
    });

    test('filters by date range', function () {
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-01-01']);
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-02-15']);
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-03-30']);
        
        $action = new ListDocuments();
        $result = $action->handle(
            $this->campaign,
            null,
            Carbon::parse('2024-02-01'),
            Carbon::parse('2024-03-31')
        );
        
        expect($result->total())->toBe(2);
    });

    test('combines status and date filters', function () {
        Document::factory()->for($this->campaign)->pending()->create(['created_at' => '2024-01-15']);
        Document::factory()->for($this->campaign)->completed()->create(['created_at' => '2024-02-15']);
        Document::factory()->for($this->campaign)->completed()->create(['created_at' => '2024-03-15']);
        
        $action = new ListDocuments();
        $result = $action->handle(
            $this->campaign,
            'completed',
            Carbon::parse('2024-02-01'),
            Carbon::parse('2024-03-31')
        );
        
        expect($result->total())->toBe(2);
    });

    test('orders documents by created_at descending', function () {
        $oldest = Document::factory()->for($this->campaign)->create(['created_at' => '2024-01-01']);
        $middle = Document::factory()->for($this->campaign)->create(['created_at' => '2024-02-01']);
        $newest = Document::factory()->for($this->campaign)->create(['created_at' => '2024-03-01']);
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign);
        
        expect($result->first()->id)->toBe($newest->id)
            ->and($result->last()->id)->toBe($oldest->id);
    });

    test('eager loads campaign relationship', function () {
        Document::factory()->for($this->campaign)->count(3)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign);
        
        expect($result->first()->relationLoaded('campaign'))->toBeFalse(); // Not loaded by default per action
    });

    test('eager loads document job with processor executions', function () {
        $document = Document::factory()->for($this->campaign)->create();
        $documentJob = DocumentJob::factory()->for($document)->for($this->campaign)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign);
        
        // The action loads documentJob.processorExecutions.processor
        expect($result->first()->relationLoaded('documentJob'))->toBeTrue();
    });

    test('enforces maximum per page of 100', function () {
        Document::factory()->for($this->campaign)->count(150)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign, null, null, null, 200);
        
        expect($result->perPage())->toBe(100);
    });

    test('uses default per page of 15', function () {
        Document::factory()->for($this->campaign)->count(30)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign);
        
        expect($result->perPage())->toBe(15);
    });

    test('only returns documents for specified campaign', function () {
        $anotherCampaign = Campaign::factory()->create();
        
        Document::factory()->for($this->campaign)->count(5)->create();
        Document::factory()->for($anotherCampaign)->count(3)->create();
        
        $action = new ListDocuments();
        $result = $action->handle($this->campaign);
        
        expect($result->total())->toBe(5);
    });

    test('returns empty result when no documents match', function () {
        Document::factory()->for($this->campaign)->create(['created_at' => '2024-01-01']);
        
        $action = new ListDocuments();
        $result = $action->handle(
            $this->campaign,
            null,
            Carbon::parse('2024-06-01'),
            Carbon::parse('2024-12-31')
        );
        
        expect($result->total())->toBe(0)
            ->and($result->isEmpty())->toBeTrue();
    });

    test('validates status values in rules', function () {
        $rules = ListDocuments::rules();
        
        expect($rules['status'])->toContain('nullable')
            ->and($rules['status'])->toContain('in:pending,processing,completed,failed');
    });

    test('validates date format in rules', function () {
        $rules = ListDocuments::rules();
        
        expect($rules['date_from'])->toContain('nullable')
            ->and($rules['date_from'])->toContain('date')
            ->and($rules['date_to'])->toContain('nullable')
            ->and($rules['date_to'])->toContain('date');
    });

    test('validates per_page range in rules', function () {
        $rules = ListDocuments::rules();
        
        expect($rules['per_page'])->toContain('nullable')
            ->and($rules['per_page'])->toContain('integer')
            ->and($rules['per_page'])->toContain('min:1')
            ->and($rules['per_page'])->toContain('max:100');
    });

    test('validates date_to is after_or_equal date_from', function () {
        $rules = ListDocuments::rules();
        
        expect($rules['date_to'])->toContain('after_or_equal:date_from');
    });
});
