<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

uses()->group('feature', 'campaign', 'web', 'tenant');

test('authenticated user can view campaign detail page', function () {
    // Setup: Create tenant and user
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $tenant->users()->attach($user->id, ['role' => 'member']);

    // Setup: Initialize tenant context and create campaign
    TenantContext::run($tenant, function () use ($user) {
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign Detail',
            'type' => 'custom',
        ]);

        // Test: Access campaign detail page
        // This will fail with SQLSTATE[42P01]: Undefined table "campaigns"
        // because TenantContext isn't properly initializing the 'tenant' connection
        $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}");

        // Assertion: Should load successfully without database errors
        expect($response->status())->toBe(200);
    });
});

test('authenticated user can view campaign edit page', function () {
    // Setup: Create tenant and user
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $tenant->users()->attach($user->id, ['role' => 'member']);

    // Setup: Initialize tenant context and create campaign
    TenantContext::run($tenant, function () use ($user) {
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign Edit',
            'type' => 'template',
        ]);

        // Test: Access campaign edit page
        // This will fail with SQLSTATE[42P01]: Undefined table "campaigns"
        $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}/edit");

        // Assertion: Should load successfully without database errors
        expect($response->status())->toBe(200);
    });
});

test('authenticated user can delete campaign', function () {
    // Setup: Create tenant and user
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $tenant->users()->attach($user->id, ['role' => 'member']);

    // Setup: Initialize tenant context and create campaign
    TenantContext::run($tenant, function () use ($user) {
        $campaign = Campaign::factory()->create([
            'name' => 'Test Campaign Delete',
            'type' => 'meta',
        ]);

        // Test: Delete campaign
        // This will fail with SQLSTATE[42P01]: Undefined table "campaigns"
        $response = $this->actingAs($user)->delete("/campaigns/{$campaign->id}");

        // Assertion: Should redirect after successful deletion
        expect($response->status())->toBe(302)
            ->and($response->getTargetUrl())->toContain('/campaigns');

        // Verify campaign was actually deleted (soft deleted)
        expect(Campaign::withoutTrashed()->find($campaign->id))->toBeNull();
    });
});
