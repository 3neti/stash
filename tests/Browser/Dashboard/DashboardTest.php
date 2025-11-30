<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Document;
use App\Models\User;

test('authenticated user can view dashboard', function () {
    $user = User::factory()->create();

    loginAsUser($user)
        ->visit('/dashboard')
        ->assertUrlPath('/dashboard')
        ->assertSee('Dashboard');
})->group('dashboard');

test('dashboard displays campaign stats', function () {
    $user = User::factory()->create();
    Campaign::factory()->count(3)->create();

    loginAsUser($user)
        ->visit('/dashboard')
        ->assertSee('Total Campaigns')
        ->assertSee('3');
})->group('dashboard');

test('dashboard displays document stats', function () {
    $user = User::factory()->create();
    Document::factory()->count(5)->create();

    loginAsUser($user)
        ->visit('/dashboard')
        ->assertSee('Total Documents')
        ->assertSee('5');
})->group('dashboard');

test('dashboard quick actions link to campaigns', function () {
    $user = User::factory()->create();

    loginAsUser($user)
        ->visit('/dashboard')
        ->assertSee('Create Campaign')
        ->click('a:has-text("Create Campaign")')
        ->assertUrlPath('/campaigns/create');
})->group('dashboard');

test('unauthenticated user is redirected from dashboard', function () {
    visit('/dashboard')
        ->assertUrlPath('/login');
})->group('dashboard');
