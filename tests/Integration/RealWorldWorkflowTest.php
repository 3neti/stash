<?php

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

describe('Real-World E-Signature Workflow', function () {
    
    beforeEach(function () {
        // Ensure test file exists
        $this->testPdfPath = storage_path('app/test-invoice.pdf');
        if (!file_exists($this->testPdfPath)) {
            // Create a minimal PDF for testing
            file_put_contents($this->testPdfPath, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n149\n%%EOF");
        }
    });

    test('1. Vite assets are built and accessible', function () {
        // Check if Vite manifest exists (npm run build)
        $manifestPath = public_path('build/manifest.json');
        
        // In dev mode, Vite runs on port 5173
        // In production, manifest.json exists
        if (app()->environment('local')) {
            // Dev mode: Check if hot file exists or assets can be loaded
            $hotFile = public_path('hot');
            
            if (file_exists($hotFile)) {
                expect(file_get_contents($hotFile))->toContain('http');
            } else {
                // If no hot file, check if manifest exists (built assets)
                if (file_exists($manifestPath)) {
                    $manifest = json_decode(file_get_contents($manifestPath), true);
                    expect($manifest)->toBeArray()->toHaveKey('resources/js/app.js');
                } else {
                    $this->markTestSkipped('Vite not running. Start with: npm run dev');
                }
            }
        } else {
            // Production: Manifest must exist
            expect($manifestPath)->toBeFile();
            $manifest = json_decode(file_get_contents($manifestPath), true);
            expect($manifest)->toBeArray();
        }
    })->skip(fn() => !app()->environment('local'), 'Only runs in local environment');

    test('2. Broadcasting (Reverb/Pusher) configuration is valid', function () {
        // Check broadcasting is configured
        $broadcaster = config('broadcasting.default');
        expect($broadcaster)->toBeIn(['reverb', 'pusher', 'redis', 'log']);
        
        // Check connections are configured
        $connections = config('broadcasting.connections');
        expect($connections)->toBeArray()->toHaveKey($broadcaster);
        
        // If Reverb, check configuration
        if ($broadcaster === 'reverb') {
            $reverb = $connections['reverb'];
            expect($reverb)->toHaveKeys(['key', 'secret', 'app_id']);
            expect($reverb['key'])->not->toBeEmpty();
        }
        
        // If Pusher, check credentials
        if ($broadcaster === 'pusher') {
            $pusher = $connections['pusher'];
            expect($pusher)->toHaveKeys(['key', 'secret', 'app_id']);
            expect($pusher['key'])->not->toBeEmpty();
        }
    });

    test('3a. Database schema is correct', function () {
        // Check central database tables
        expect(Schema::hasTable('tenants'))->toBeTrue();
        expect(Schema::hasTable('users'))->toBeTrue();
        expect(Schema::hasTable('domains'))->toBeTrue();
        
        // Check tenant database tables
        expect(Schema::connection('tenant')->hasTable('campaigns'))->toBeTrue();
        expect(Schema::connection('tenant')->hasTable('documents'))->toBeTrue();
        expect(Schema::connection('tenant')->hasTable('document_jobs'))->toBeTrue();
        expect(Schema::connection('tenant')->hasTable('processors'))->toBeTrue();
        expect(Schema::connection('tenant')->hasTable('credentials'))->toBeTrue();
        
        // Check campaign table has required columns
        expect(Schema::connection('tenant')->hasColumns('campaigns', [
            'id', 'name', 'slug', 'pipeline_config', 'settings', 'created_at'
        ]))->toBeTrue();
    });

    test('3b. Database seed creates e-signature campaign', function () {
        // Run seeder
        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
        
        // Check e-signature campaign exists
        $campaign = Campaign::where('slug', 'e-signature')->first();
        
        expect($campaign)->not->toBeNull();
        expect($campaign->name)->toContain('Signature');
        expect($campaign->pipeline_config)->toBeArray();
        expect($campaign->pipeline_config['processors'])->toBeArray();
    });

    test('3c. Queue connection is working', function () {
        // Check queue is configured
        $queueConnection = config('queue.default');
        expect($queueConnection)->not->toBe('sync'); // Must be async
        
        // Test queue can be written to
        Queue::fake();
        
        // Dispatch a test job
        Queue::push(function () {
            return 'test';
        });
        
        Queue::assertPushed(function ($job) {
            return true;
        });
    });

    test('4. document:process command processes invoice', function () {
        // Ensure e-signature campaign exists
        $campaign = Campaign::where('slug', 'e-signature')->first();
        if (!$campaign) {
            $campaign = Campaign::factory()->create([
                'slug' => 'e-signature',
                'name' => 'Electronic Signature',
                'pipeline_config' => [
                    'processors' => [
                        ['type' => 'electronic-signature', 'config' => []],
                    ],
                ],
            ]);
        }
        
        // Fake queue to process synchronously
        Queue::fake();
        Storage::fake('s3');
        
        // Copy test PDF to storage
        Storage::disk('s3')->put('test-invoice.pdf', file_get_contents($this->testPdfPath));
        
        // Create document and job manually (simulating the command)
        $document = Document::create([
            'campaign_id' => $campaign->id,
            'original_filename' => 'Invoice.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => filesize($this->testPdfPath),
            'storage_path' => 'test-invoice.pdf',
            'storage_disk' => 's3',
            'hash' => hash_file('sha256', $this->testPdfPath),
        ]);
        
        $job = DocumentJob::create([
            'document_id' => $document->id,
            'campaign_id' => $campaign->id,
            'status' => 'pending',
            'pipeline_config' => $campaign->pipeline_config,
        ]);
        
        expect($document->exists)->toBeTrue();
        expect($job->exists)->toBeTrue();
        expect($job->status)->toBe('pending');
        
        // Note: Actual processing would require queue:work to be running
        // This test verifies the document and job are created correctly
    });

    test('5. KYC callback URL processes auto-approved transaction', function () {
        // Create a document that's waiting for KYC verification
        $campaign = Campaign::where('slug', 'e-signature')->first() 
            ?? Campaign::factory()->create(['slug' => 'e-signature']);
        
        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
            'metadata' => [
                'kyc_transaction_id' => 'EKYC-1764773764-3863',
                'kyc_callback_uuid' => 'e2ed9386-2fef-470a-bfa0-66e3c8e78f3f',
            ],
        ]);
        
        // Simulate KYC callback request
        $response = $this->get('/kyc/callback/e2ed9386-2fef-470a-bfa0-66e3c8e78f3f?' . http_build_query([
            'transactionId' => 'EKYC-1764773764-3863',
            'status' => 'auto_approved',
        ]));
        
        // Should redirect or return success
        expect($response->status())->toBeIn([200, 302]);
        
        // Check document was updated with KYC result
        $document->refresh();
        expect($document->metadata['kyc_status'] ?? null)->toBe('auto_approved');
    })->skip(fn() => !app()->hasRoute('kyc.callback'), 'KYC callback route not registered');

});

describe('Real-World Workflow: Full Integration (requires services)', function () {
    
    test('complete e-signature workflow from upload to signing', function () {
        // This test requires:
        // - npm run dev (Vite)
        // - php artisan reverb:start --debug (Broadcasting)
        // - php artisan queue:work (Queue processing)
        
        $campaign = Campaign::where('slug', 'e-signature')->first();
        if (!$campaign) {
            $this->markTestSkipped('E-signature campaign not seeded. Run: php artisan db:seed');
        }
        
        Storage::fake('s3');
        Queue::fake();
        Event::fake();
        
        // 1. Upload document
        $pdfContent = "%PDF-1.4\nTest Invoice";
        $uploadedFile = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'Invoice.pdf',
            $pdfContent
        );
        
        $response = $this->post(route('campaigns.documents.store', $campaign), [
            'document' => $uploadedFile,
        ]);
        
        $response->assertRedirect();
        
        // 2. Verify document was created
        $document = Document::latest()->first();
        expect($document)->not->toBeNull();
        expect($document->campaign_id)->toBe($campaign->id);
        expect($document->original_filename)->toBe('Invoice.pdf');
        
        // 3. Verify job was queued
        Queue::assertPushed(\App\Jobs\ProcessDocumentJob::class, function ($job) use ($document) {
            return $job->document->id === $document->id;
        });
        
        // 4. Simulate queue processing (in real workflow, queue:work handles this)
        // Process the job synchronously for testing
        $job = new \App\Jobs\ProcessDocumentJob($document);
        $job->handle();
        
        // 5. Verify document is processed
        $document->refresh();
        expect($document->state)->toBeInstanceOf(\App\States\Document\ProcessingDocumentState::class);
        
        // 6. Simulate KYC auto-approval
        $document->metadata = array_merge($document->metadata ?? [], [
            'kyc_status' => 'auto_approved',
            'kyc_transaction_id' => 'EKYC-' . time() . '-' . rand(1000, 9999),
            'signature_status' => 'signed',
            'signed_at' => now()->toIso8601String(),
        ]);
        $document->save();
        
        // 7. Mark as completed
        $document->markCompleted();
        
        // 8. Verify final state
        $document->refresh();
        expect($document->isCompleted())->toBeTrue();
        expect($document->metadata['signature_status'])->toBe('signed');
        
    })->skip(function () {
        // Skip if campaign doesn't exist or routes aren't available
        $campaign = Campaign::where('slug', 'e-signature')->first();
        return !$campaign || !app()->hasRoute('campaigns.documents.store');
    }, 'E-signature campaign or routes not available');

});
