<?php

use App\Models\Campaign;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('Document Ingestion API', function () {
    beforeEach(function () {
        Storage::fake('tenant');
        Queue::fake();
        
        $this->campaign = Campaign::factory()->create();
    });

    test('POST /api/campaigns/{campaign}/documents - uploads document successfully', function () {
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/documents", [
            'file' => $file,
            'metadata' => ['source' => 'api-test'],
        ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'uuid',
                'campaign_id',
                'original_filename',
                'mime_type',
                'size_bytes',
            ])
            ->assertJson([
                'campaign_id' => $this->campaign->id,
                'original_filename' => 'test.pdf',
                'mime_type' => 'application/pdf',
            ]);
        
        // Verify document was created
        $document = Document::where('campaign_id', $this->campaign->id)->first();
        expect($document)->not->toBeNull();
    });

    test('POST /api/campaigns/{campaign}/documents - validates file is required', function () {
        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/documents", [
            'metadata' => ['source' => 'test'],
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('POST /api/campaigns/{campaign}/documents - validates file type', function () {
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');
        
        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/documents", [
            'file' => $file,
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('POST /api/campaigns/{campaign}/documents - validates file size', function () {
        $file = UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'); // Over 10MB
        
        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/documents", [
            'file' => $file,
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('GET /api/campaigns/{campaign}/documents - lists documents', function () {
        Document::factory()->count(3)->for($this->campaign)->create();
        
        $response = $this->getJson("/api/campaigns/{$this->campaign->id}/documents");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
                'links',
            ]);
    });

    test('GET /api/campaigns/{campaign}/documents - filters by status', function () {
        Document::factory()->for($this->campaign)->pending()->count(2)->create();
        Document::factory()->for($this->campaign)->completed()->count(3)->create();
        
        $response = $this->getJson("/api/campaigns/{$this->campaign->id}/documents?status=completed");
        
        $response->assertStatus(200);
    });

    test('GET /api/campaigns/{campaign}/documents - paginates results', function () {
        Document::factory()->count(25)->for($this->campaign)->create();
        
        $response = $this->getJson("/api/campaigns/{$this->campaign->id}/documents?per_page=10&page=1");
        
        $response->assertStatus(200);
    });

    test('GET /api/documents/{uuid} - retrieves document status', function () {
        $document = Document::factory()->for($this->campaign)->create();
        
        $response = $this->getJson("/api/documents/{$document->uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'id' => $document->id,
                'uuid' => $document->uuid,
                'campaign_id' => $this->campaign->id,
            ]);
    });

    test('GET /api/documents/{uuid} - returns 404 for invalid UUID', function () {
        $response = $this->getJson('/api/documents/00000000-0000-0000-0000-000000000000');
        
        $response->assertStatus(404);
    });

    test('GET /api/documents/{uuid} - validates UUID format', function () {
        $response = $this->getJson('/api/documents/invalid-uuid');
        
        $response->assertStatus(404); // Laravel returns 404 for route parameter mismatch
    });
});
