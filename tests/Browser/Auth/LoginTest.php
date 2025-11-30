<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('user can log in', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    visit('/login')
        ->type('email', 'test@example.com')
        ->type('password', 'password')
        ->click('button[type="submit"]')
        ->assertUrlPath('/dashboard');
})->group('auth');

test('user sees validation error for invalid credentials', function () {
    visit('/login')
        ->type('email', 'nonexistent@example.com')
        ->type('password', 'wrong-password')
        ->click('button[type="submit"]')
        ->assertSee('These credentials do not match our records');
})->group('auth');

test('user can register', function () {
    visit('/register')
        ->type('name', 'John Doe')
        ->type('email', 'john@example.com')
        ->type('password', 'password')
        ->type('password_confirmation', 'password')
        ->click('button[type="submit"]')
        ->assertUrlPath('/verify-email');
})->group('auth');

test('authenticated user can log out', function () {
    $user = User::factory()->create();

    loginAsUser($user)
        ->visit('/dashboard')
        ->click('[data-testid="user-menu"]')
        ->click('[data-testid="logout-button"]')
        ->assertUrlPath('/')
        ->assertSee('Log in');
})->group('auth');
