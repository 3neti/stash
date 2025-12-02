<?php

use App\Actions\Documents\UploadDocument;
use App\Models\Campaign;
use App\Models\Document;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesDashboardSetup;

uses(UsesDashboardSetup::class)
    ->group('feature', 'document', 'action', 'tenant');

describe('UploadDocument Action', function () {
    beforeEach(function () {
        // Setup tenant with database
        [$tenant, $user] = $this->setupDashboardTestTenant();
        $this->tenant = $tenant;
        $this->user = $user;

        Storage::fake('tenant');
        Queue::fake();
    });

    test('uploads a PDF document successfully', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file, ['source' => 'api']);

            expect($document)->toBeInstanceOf(Document::class)
                ->and($document->campaign_id)->toBe($campaign->id)
                ->and($document->mime_type)->toBe('application/pdf')
                ->and($document->size_bytes)->toBeGreaterThan(0) // Fake file size varies
                ->and($document->original_filename)->toBe('document.pdf')
                ->and($document->metadata)->toBe(['source' => 'api'])
                ->and($document->hash)->not->toBeEmpty()
                ->and($document->storage_path)->toContain('tenants/')
                ->and($document->storage_disk)->toBe('tenant');

            // Document uploaded successfully (workflow starts automatically via pipeline)
        });
    });

    test('uploads image documents successfully', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->image('scan.png')->size(512);

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file);

            expect($document->mime_type)->toBe('image/png')
                ->and($document->size_bytes)->toBeGreaterThan(0);

            // Workflow starts automatically
        });
    });

    test('calculates SHA-256 hash correctly', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
            $expectedHash = hash_file('sha256', $file->getRealPath());

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file);

            expect($document->hash)->toBe($expectedHash);
        });
    });

    test('generates tenant-scoped storage path', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file);

            $year = now()->format('Y');
            $month = now()->format('m');

            expect($document->storage_path)->toMatch('/tenants\/.*\/documents\/'.$year.'\/'.$month.'\/.*\.pdf$/');
        });
    });

    test('sanitizes filename in storage path', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('My Document (2024).pdf', 100, 'application/pdf');

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file);

            expect($document->storage_path)->toContain('my-document-2024');
        });
    });

    test('stores file to tenant disk', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file);

            Storage::disk('tenant')->assertExists($document->storage_path);
        });
    });

    test('stores optional metadata', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
            $metadata = [
                'source' => 'api',
                'client_version' => '1.0.0',
                'tags' => ['invoice', 'urgent'],
            ];

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file, $metadata);

            expect($document->metadata)->toBe($metadata);
        });
    });

    test('handles null metadata', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file, null);

            expect($document->metadata)->toBe([]);
        });
    });

    test('generates unique ULID for document ID', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file1 = UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf');
            $file2 = UploadedFile::fake()->create('doc2.pdf', 100, 'application/pdf');

            $action = new UploadDocument;
            $document1 = $action->handle($campaign, $file1);
            $document2 = $action->handle($campaign, $file2);

            expect($document1->id)->not->toBe($document2->id)
                ->and(strlen($document1->id))->toBe(26) // ULID length
                ->and(strlen($document2->id))->toBe(26);
        });
    });

    test('generates unique UUID for document', function () {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file);

            expect($document->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
        });
    });


    test('accepts all supported mime types', function (string $extension, string $mimeType) {
        TenantContext::run($this->tenant, function () use ($extension, $mimeType) {
            $campaign = Campaign::factory()->create();
            $file = UploadedFile::fake()->create("document.{$extension}", 100, $mimeType);

            $action = new UploadDocument;
            $document = $action->handle($campaign, $file);

            expect($document)->toBeInstanceOf(Document::class)
                ->and($document->mime_type)->toBe($mimeType);
        });
    })->with([
        ['pdf', 'application/pdf'],
        ['png', 'image/png'],
        ['jpg', 'image/jpeg'],
        ['jpeg', 'image/jpeg'],
        ['tiff', 'image/tiff'],
    ]);

    test('validates file is required', function () {
        $rules = UploadDocument::rules();

        expect($rules['file'])->toContain('required')
            ->and($rules['file'])->toContain('file');
    });

    test('validates file mime types', function () {
        $rules = UploadDocument::rules();

        expect($rules['file'])->toContain('mimes:pdf,png,jpg,jpeg,tiff');
    });

    test('validates file max size is 10MB', function () {
        $rules = UploadDocument::rules();

        expect($rules['file'])->toContain('max:10240');
    });

    test('validates metadata is optional array', function () {
        $rules = UploadDocument::rules();

        expect($rules['metadata'])->toContain('nullable')
            ->and($rules['metadata'])->toContain('array');
    });
});
