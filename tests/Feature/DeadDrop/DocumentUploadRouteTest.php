<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Document;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Tests\Support\UsesDashboardSetup;

uses(UsesDashboardSetup::class)
    ->group('feature', 'document', 'campaign', 'web', 'tenant');

test('authenticated user can upload document to campaign', function () {
    // Setup: Create tenant with database, migrations, and test user
    [$tenant, $user] = $this->setupDashboardTestTenant();

    // Create campaign in tenant context
    TenantContext::run($tenant, function () use ($user) {
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign for Upload',
            'type' => 'custom',
            'state' => \App\States\Campaign\ActiveCampaignState::class,
        ]);

        // Test: Upload document to campaign
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        
        $response = $this->actingAs($user)->post("/campaigns/{$campaign->id}/documents", [
            'file' => $file,
        ]);

        // Assertion: Should upload successfully without database errors
        expect($response->status())->toBe(201);
    });
});

test('authenticated user can retrieve uploaded documents for campaign', function () {
    // Setup: Create tenant with database, migrations, and test user
    [$tenant, $user] = $this->setupDashboardTestTenant();

    // Create campaign + document in tenant context
    TenantContext::run($tenant, function () use ($user) {
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign for Documents List',
            'type' => 'custom',
            'state' => \App\States\Campaign\ActiveCampaignState::class,
        ]);

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'state' => 'completed',
        ]);

        // Test: Retrieve documents for campaign
        // This fails with SQLSTATE[42P01]: Undefined table: "documents"
        $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}/documents");

        // Assertion: Should retrieve successfully without database errors
        expect($response->status())->toBe(200);
    });
});

test('authenticated user can access document detail page', function () {
    // Setup: Create tenant with database, migrations, and test user
    [$tenant, $user] = $this->setupDashboardTestTenant();

    // Create campaign + document in tenant context
    TenantContext::run($tenant, function () use ($user) {
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign for Document Detail',
            'type' => 'custom',
            'state' => \App\States\Campaign\ActiveCampaignState::class,
        ]);

        $document = Document::factory()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'state' => 'processing',
        ]);

        // Test: Access document detail page
        // This fails with SQLSTATE[42P01]: Undefined table
        $response = $this->actingAs($user)->get("/documents/{$document->uuid}");

        // Assertion: Should load successfully without database errors
        expect($response->status())->toBe(200);
    });
});
