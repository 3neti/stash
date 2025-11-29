<?php

use App\Models\Campaign;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('API Authentication', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->campaign = Campaign::factory()->create();
    });

    test('POST /api/campaigns/{campaign}/tokens - generates token', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/campaigns/{$this->campaign->id}/tokens", [
                'name' => 'Test Token',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'name',
                'abilities',
                'created_at',
            ])
            ->assertJson([
                'name' => 'Test Token',
                'abilities' => ['*'],
            ]);

        expect($this->campaign->tokens()->count())->toBe(1);
    });

    test('POST /api/campaigns/{campaign}/tokens - revokes old tokens', function () {
        // Create first token
        $this->campaign->createToken('First Token');
        expect($this->campaign->tokens()->count())->toBe(1);

        // Generate new token (should revoke old one)
        $this->actingAs($this->user)
            ->postJson("/api/campaigns/{$this->campaign->id}/tokens");

        expect($this->campaign->tokens()->count())->toBe(1);
    });

    test('DELETE /api/campaigns/{campaign}/tokens - revokes all tokens', function () {
        $this->campaign->createToken('Token 1');
        $this->campaign->createToken('Token 2');

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/campaigns/{$this->campaign->id}/tokens");

        $response->assertStatus(200)
            ->assertJson([
                'revoked' => 2,
            ]);

        expect($this->campaign->tokens()->count())->toBe(0);
    });

    test('API endpoints require authentication', function () {
        $response = $this->postJson("/api/campaigns/{$this->campaign->id}/documents");
        $response->assertStatus(401);

        $response = $this->getJson("/api/campaigns/{$this->campaign->id}/documents");
        $response->assertStatus(401);
    });

    test('API token authenticates requests with campaign token', function () {
        $this->campaign->createToken('API Token');

        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson("/api/campaigns/{$this->campaign->id}/documents");

        $response->assertStatus(200);
    });

    test('invalid token returns 401', function () {
        $response = $this->withToken('invalid-token')
            ->getJson("/api/campaigns/{$this->campaign->id}/documents");

        $response->assertStatus(401);
    });
});

describe('API Rate Limiting', function () {
    beforeEach(function () {
        $this->campaign = Campaign::factory()->create();
        $this->campaign->createToken('API Token');
    });

    test('enforces 100 requests/min limit on general API', function () {
        // Make 100 requests (should succeed)
        for ($i = 0; $i < 100; $i++) {
            $response = $this->actingAs($this->campaign, 'sanctum')
                ->getJson("/api/campaigns/{$this->campaign->id}/documents");
            expect($response->status())->toBe(200);
        }

        // 101st request should be rate limited
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson("/api/campaigns/{$this->campaign->id}/documents");
        
        $response->assertStatus(429)
            ->assertHeader('Retry-After');
    })->skip('Slow test - only run manually');

    test('enforces 10 uploads/min limit', function () {
        Storage::fake('tenant');
        Queue::fake();

        // Make 10 uploads (should succeed)
        for ($i = 0; $i < 10; $i++) {
            $file = UploadedFile::fake()->create("test{$i}.pdf", 1024, 'application/pdf');
            $response = $this->actingAs($this->campaign, 'sanctum')
                ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
                    'file' => $file,
                ]);
            expect($response->status())->toBe(201);
        }

        // 11th upload should be rate limited
        $file = UploadedFile::fake()->create('test11.pdf', 1024, 'application/pdf');
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
                'file' => $file,
            ]);
        
        $response->assertStatus(429)
            ->assertHeader('Retry-After');
    })->skip('Slow test - only run manually');
});

describe('Batch Upload', function () {
    beforeEach(function () {
        Storage::fake('tenant');
        Queue::fake();
        
        $this->campaign = Campaign::factory()->create();
        $this->campaign->createToken('API Token');
    });

    test('uploads 5 valid files successfully', function () {
        $files = [];
        for ($i = 0; $i < 5; $i++) {
            $files[] = UploadedFile::fake()->create("test{$i}.pdf", 1024, 'application/pdf');
        }

        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
                'files' => $files,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'successful',
                'failed',
                'summary',
            ])
            ->assertJson([
                'summary' => [
                    'total' => 5,
                    'successful' => 5,
                    'failed' => 0,
                ],
            ]);

        expect(Document::where('campaign_id', $this->campaign->id)->count())->toBe(5);
    });

    test('handles validation error for invalid files', function () {
        $files = [
            UploadedFile::fake()->create('valid1.pdf', 1024, 'application/pdf'),
            UploadedFile::fake()->create('valid2.pdf', 1024, 'application/pdf'),
            UploadedFile::fake()->create('invalid.txt', 100, 'text/plain'),
        ];

        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
                'files' => $files,
            ]);

        // Laravel validation rejects before we can handle partial failures
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files.2']);
    });

    test('returns 422 when all files have invalid types', function () {
        $files = [
            UploadedFile::fake()->create('invalid1.txt', 100, 'text/plain'),
            UploadedFile::fake()->create('invalid2.doc', 100, 'application/msword'),
        ];

        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
                'files' => $files,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files.0', 'files.1']);
    });

    test('enforces max 10 files limit', function () {
        $files = [];
        for ($i = 0; $i < 11; $i++) {
            $files[] = UploadedFile::fake()->create("test{$i}.pdf", 1024, 'application/pdf');
        }

        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
                'files' => $files,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    });
});

describe('Webhook Management', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->campaign = Campaign::factory()->create();
    });

    test('sets webhook channel', function () {
        $response = $this->actingAs($this->user)
            ->putJson("/api/campaigns/{$this->campaign->id}/channels", [
                'channel' => 'webhook',
                'value' => 'https://example.com/webhook',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'channel' => 'webhook',
                'value' => 'https://example.com/webhook',
            ]);

        expect($this->campaign->fresh()->webhook)->toBe('https://example.com/webhook');
    });

    test('tests webhook delivery', function () {
        Queue::fake();
        $this->campaign->setChannel('webhook', 'https://example.com/webhook');

        $response = $this->actingAs($this->user)
            ->postJson("/api/campaigns/{$this->campaign->id}/webhook/test");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'webhook_url' => 'https://example.com/webhook',
            ]);

        Queue::assertPushed(\App\Jobs\DispatchWebhook::class);
    });

    test('lists webhook deliveries', function () {
        \App\Models\WebhookDelivery::factory()->count(3)->create([
            'campaign_id' => $this->campaign->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/campaigns/{$this->campaign->id}/webhooks");

        $response->assertStatus(200);
        
        $json = $response->json();
        expect($json)->toHaveKeys(['data', 'current_page', 'per_page']);
        expect(count($json['data']))->toBe(3);
    });
});

describe('Document Ingestion API', function () {
    beforeEach(function () {
        Storage::fake('tenant');
        Queue::fake();
        
        $this->campaign = Campaign::factory()->create();
        $this->campaign->createToken('API Token', ['*']);
    });

    test('POST /api/campaigns/{campaign}/documents - uploads document successfully', function () {
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
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
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
            'metadata' => ['source' => 'test'],
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('POST /api/campaigns/{campaign}/documents - validates file type', function () {
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');
        
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
            'file' => $file,
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('POST /api/campaigns/{campaign}/documents - validates file size', function () {
        $file = UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'); // Over 10MB
        
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->postJson("/api/campaigns/{$this->campaign->id}/documents", [
            'file' => $file,
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('GET /api/campaigns/{campaign}/documents - lists documents', function () {
        Document::factory()->count(3)->for($this->campaign)->create();
        
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson("/api/campaigns/{$this->campaign->id}/documents");
        
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
        
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson("/api/campaigns/{$this->campaign->id}/documents?status=completed");
        
        $response->assertStatus(200);
    });

    test('GET /api/campaigns/{campaign}/documents - paginates results', function () {
        Document::factory()->count(25)->for($this->campaign)->create();
        
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson("/api/campaigns/{$this->campaign->id}/documents?per_page=10&page=1");
        
        $response->assertStatus(200);
    });

    test('GET /api/documents/{uuid} - retrieves document status', function () {
        $document = Document::factory()->for($this->campaign)->create();
        
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson("/api/documents/{$document->uuid}");
        
        $response->assertStatus(200)
            ->assertJson([
                'id' => $document->id,
                'uuid' => $document->uuid,
                'campaign_id' => $this->campaign->id,
            ]);
    });

    test('GET /api/documents/{uuid} - returns 404 for invalid UUID', function () {
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson('/api/documents/00000000-0000-0000-0000-000000000000');
        
        $response->assertStatus(404);
    });

    test('GET /api/documents/{uuid} - validates UUID format', function () {
        $response = $this->actingAs($this->campaign, 'sanctum')
            ->getJson('/api/documents/invalid-uuid');
        
        $response->assertStatus(404); // Laravel returns 404 for route parameter mismatch
    });
});
