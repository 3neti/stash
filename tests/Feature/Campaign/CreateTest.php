<?php

use App\Models\User;

uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class);

test('authenticated user can create campaign with valid enum type', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->post('/campaigns', [
            'name' => 'My Test Campaign',
            'description' => 'Test campaign via HTTP',
            'type' => 'custom', // Valid enum value
        ]);

    $response
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('authenticated user cannot create campaign with invalid enum type', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this
        ->actingAs($user)
        ->post('/campaigns', [
            'name' => 'My Test Campaign',
            'type' => 'general', // Invalid - this was the bug
        ]);

    // Should return back with validation errors
    $response->assertRedirect()
        ->assertSessionHasErrors('type');
});

test('campaign creation validates all valid enum types', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    
    foreach (['template', 'custom', 'meta'] as $type) {
        $response = $this
            ->actingAs($user)
            ->post('/campaigns', [
                'name' => "Campaign - {$type}",
                'type' => $type,
            ]);

        expect($response->status())->toBe(302); // Redirect means success
    }
});
