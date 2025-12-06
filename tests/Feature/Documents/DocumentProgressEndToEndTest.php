<?php

declare(strict_types=1);


use App\Models\Campaign;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class);

class DocumentProgressEndToEndTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Scenario: User uploads a document and requests progress via API.
     * Expected: Progress endpoint should properly initialize tenant context and return progress data.
     */
    public function test_document_progress_api_initializes_tenant_context_from_user(): void
    {
        // Setup: Create tenant
        $tenant = Tenant::factory()->create(['name' => 'Test Tenant']);

        // Setup: Create and associate user with tenant
        $user = User::factory()->create(['email' => 'test@example.com']);
        $tenant->users()->attach($user->id, ['role' => 'member']);

        // Setup: Create campaign with processors in tenant
        TenantContext::run($tenant, function () {
            // Seed processors and campaign
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);

            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'CampaignSeeder',
                '--no-interaction' => true,
            ]);
        });

        // Act: Create document in tenant
        $document = null;
        TenantContext::run($tenant, function () use (&$document) {
            $campaign = Campaign::first();
            $document = Document::factory()->create([
                'campaign_id' => $campaign->id,
            ]);
        });

        // Verify precondition: User belongs to tenant
        $this->assertTrue($user->belongsToTenant($tenant));
        $this->assertEquals($tenant->id, $user->tenants()->first()->id);

        // Act: Call progress API as authenticated user
        $response = $this->actingAs($user)->getJson("/api/documents/{$document->uuid}/progress");

        // Assert: Should succeed (not 500)
        $this->assertNotEquals(500, $response->status(), 'Progress API should not return 500. Response: ' . $response->getContent());

        // Assert: Response is JSON and has status field
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('percentage_complete', $data);
    }

    /**
     * Scenario: User uploads a document and requests metrics via API.
     * Expected: Metrics endpoint should properly initialize tenant context and return metrics data.
     */
    public function test_document_metrics_api_initializes_tenant_context_from_user(): void
    {
        // Setup: Create tenant
        $tenant = Tenant::factory()->create(['name' => 'Test Tenant']);

        // Setup: Create and associate user with tenant
        $user = User::factory()->create(['email' => 'metrics@example.com']);
        $tenant->users()->attach($user->id, ['role' => 'member']);

        // Setup: Create campaign and document in tenant
        TenantContext::run($tenant, function () {
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);

            \Illuminate\Support\Facades\Artisan::call('db:seed', [
                '--class' => 'CampaignSeeder',
                '--no-interaction' => true,
            ]);
        });

        $document = null;
        TenantContext::run($tenant, function () use (&$document) {
            $campaign = Campaign::first();
            $document = Document::factory()->create([
                'campaign_id' => $campaign->id,
            ]);
        });

        // Act: Call metrics API as authenticated user
        $response = $this->actingAs($user)->getJson("/api/documents/{$document->uuid}/metrics");

        // Assert: Should not return 500 (tenant context should be initialized)
        $this->assertNotEquals(500, $response->status(), 'Metrics API should not return 500. Response: ' . $response->getContent());

        // Assert: Should return 404 or 200 (no error from missing tenant context)
        $this->assertTrue(
            in_array($response->status(), [200, 404]),
            "Expected status 200 or 404, got {$response->status()}"
        );
    }

    /**
     * Scenario: User without tenant_id tries to access progress API.
     * Expected: Should handle gracefully without 500 error (or fail with appropriate error).
     */
    public function test_document_progress_api_handles_user_without_tenant(): void
    {
        // Setup: Create user WITHOUT tenant association
        $user = User::factory()->create(['email' => 'no-tenant@example.com']);

        // Setup: Create a document (in any tenant context)
        $tenant = Tenant::factory()->create();
        $document = null;
        TenantContext::run($tenant, function () use (&$document) {
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        });

        // Act: Call progress API as user without tenant
        $response = $this->actingAs($user)->getJson("/api/documents/{$document->uuid}/progress");

        // Assert: Should not return 500 (handle missing tenant gracefully)
        // Either 404 (document not found due to no tenant context)
        // or 401/403 (unauthorized access)
        // But NOT 500 (internal server error)
        $this->assertNotEquals(500, $response->status(), 'API should handle user without tenant gracefully, not return 500');
    }

    /**
     * Scenario: Unauthenticated user tries to access progress API.
     * Expected: Should return 401 (unauthorized), not 500.
     */
    public function test_document_progress_api_requires_authentication(): void
    {
        // Setup
        $tenant = Tenant::factory()->create();
        $document = null;
        TenantContext::run($tenant, function () use (&$document) {
            \Illuminate\Support\Facades\Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        });

        // Act: Call progress API without authentication
        $response = $this->getJson("/api/documents/{$document->uuid}/progress");

        // Assert: Should return 401, not 500
        $this->assertEquals(401, $response->status(), 'Unauthenticated users should get 401, not 500');
    }
}
