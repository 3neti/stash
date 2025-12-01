<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;

test('browser request: campaign show page loads without SQLSTATE error', function () {
    // Setup: Create tenant with user and campaign
    $tenant = Tenant::factory()->create(['name' => 'Browser Test Tenant']);
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'browser-test@example.com',
        'email_verified_at' => now(),
    ]);

    // Run migrations for this tenant
    TenantContext::run($tenant, function () {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    });

    // Create a campaign
    $campaign = null;
    TenantContext::run($tenant, function () use (&$campaign) {
        $campaign = Campaign::factory()->create([
            'name' => 'Browser Test Campaign',
            'status' => 'active',
        ]);
    });

    expect($campaign)->not->toBeNull();
    expect($campaign->id)->toBeTruthy();

    // Simulate browser request: User logs in and navigates to campaign
    $this->actingAs($user)
        ->get("/campaigns/{$campaign->id}")
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('campaigns/Show')
            ->has('campaign')
        );
});
