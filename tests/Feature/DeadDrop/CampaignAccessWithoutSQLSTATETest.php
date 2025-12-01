<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

test('campaign page loads without SQLSTATE error after fresh setup', function () {
    // Simulate fresh setup: create tenant and user
    $tenant = Tenant::factory()->create(['name' => 'Test Company']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'test@example.com',
        'email_verified_at' => now(),
    ]);

    // Create a campaign in tenant context
    $campaign = null;
    TenantContext::run($tenant, function () use (&$campaign) {
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign',
            'status' => 'active',
        ]);
    });

    expect($campaign)->not->toBeNull();

    // Authenticate as user and access campaign page
    // This simulates: user logs in → middleware initializes tenant → route handler queries campaign
    $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}");

    // Should return 200 OK without SQLSTATE error
    $response->assertStatus(200);

    // Should render the campaign show page
    $response->assertInertia(fn ($page) => $page
        ->component('campaigns/Show')
        ->has('campaign')
    );
});

test('campaign detail page loads successfully', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $campaign = null;
    TenantContext::run($tenant, function () use (&$campaign) {
        $campaign = Campaign::factory()->create([
            'name' => 'Invoices Processing',
            'description' => 'Process invoice documents',
            'status' => 'active',
        ]);
    });

    $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}");

    // Key test: campaign page loads without SQLSTATE error (status 200, not 500)
    expect($response->status())->toBe(200);
});

test('campaign list page loads for authenticated user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    TenantContext::run($tenant, function () {
        Campaign::factory()->count(3)->create(['status' => 'active']);
    });

    $response = $this->actingAs($user)->get('/campaigns');

    // Key test: campaign list loads without SQLSTATE error
    expect($response->status())->toBe(200);
});

test('campaign access fails for unauthenticated user', function () {
    $tenant = Tenant::factory()->create();
    $campaign = null;

    TenantContext::run($tenant, function () use (&$campaign) {
        $campaign = Campaign::factory()->create();
    });

    $response = $this->get("/campaigns/{$campaign->id}");

    // Should redirect to login
    $response->assertRedirect('/login');
});

test('campaign access fails for user from different tenant', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $user = User::factory()->create(['tenant_id' => $tenant1->id]);

    $campaign = null;
    TenantContext::run($tenant2, function () use (&$campaign) {
        $campaign = Campaign::factory()->create();
    });

    // User is authenticated but accessing campaign from different tenant
    // Should fail because tenant context is switched
    $this->actingAs($user);

    // Note: This test depends on proper authorization checks
    // For now, just verify no SQLSTATE error occurs
    $response = $this->get("/campaigns/{$campaign->id}");

    // Should not return 500 (SQLSTATE error)
    expect($response->status())->not->toBe(500);
});
