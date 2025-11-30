<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\User;

test('user can view campaigns list', function () {
    $user = User::factory()->create();
    Campaign::factory()->count(3)->create();

    loginAsUser($user)
        ->visit('/campaigns')
        ->assertUrlPath('/campaigns')
        ->assertSee('Campaigns');
})->group('campaigns');

test('user can create campaign', function () {
    $user = User::factory()->create();

    loginAsUser($user)
        ->visit('/campaigns/create')
        ->type('name', 'Test Campaign')
        ->type('description', 'Test campaign description')
        ->click('button[type="submit"]')
        ->assertUrlPath('/campaigns/1')
        ->assertSee('Test Campaign');
})->group('campaigns');

test('user can view campaign details', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'name' => 'My Campaign',
        'description' => 'Campaign description',
    ]);

    loginAsUser($user)
        ->visit('/campaigns/'.$campaign->id)
        ->assertSee('My Campaign')
        ->assertSee('Campaign description');
})->group('campaigns');

test('user can edit campaign', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'name' => 'Original Name',
    ]);

    loginAsUser($user)
        ->visit('/campaigns/'.$campaign->id.'/edit')
        ->clear('name')
        ->type('name', 'Updated Campaign Name')
        ->click('button[type="submit"]')
        ->assertUrlPath('/campaigns/'.$campaign->id)
        ->assertSee('Updated Campaign Name');
})->group('campaigns');

test('user can delete campaign with confirmation', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create([
        'name' => 'Campaign to Delete',
    ]);

    loginAsUser($user)
        ->visit('/campaigns/'.$campaign->id)
        ->click('[data-testid="delete-button"]')
        ->assertSee('Are you sure')
        ->click('[data-testid="confirm-delete"]')
        ->assertUrlPath('/campaigns')
        ->assertDontSee('Campaign to Delete');
})->group('campaigns');

test('campaign list is filtered by status', function () {
    $user = User::factory()->create();
    Campaign::factory()->create(['name' => 'Active Campaign', 'status' => 'active']);
    Campaign::factory()->create(['name' => 'Draft Campaign', 'status' => 'draft']);

    loginAsUser($user)
        ->visit('/campaigns?status=active')
        ->assertSee('Active Campaign')
        ->assertDontSee('Draft Campaign');
})->group('campaigns');
