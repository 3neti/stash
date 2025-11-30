<?php

declare(strict_types=1);

use App\Models\User;
use Pest\Testing\Browser;

/**
 * Login as a specific user in browser tests
 */
function loginAsUser(User $user): Browser
{
    return visit('/login')
        ->type('email', $user->email)
        ->type('password', 'password')
        ->click('button[type="submit"]');
}

/**
 * Assert that a user is authenticated in browser tests
 */
function assertUserAuthenticated(Browser $browser, User $user): Browser
{
    return $browser
        ->visit('/dashboard')
        ->assertUrlPath('/dashboard');
}

/**
 * Create a test user and log them in
 */
function loginTestUser(array $attributes = []): Browser
{
    $user = User::factory()->create([
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        ...$attributes,
    ]);

    return loginAsUser($user);
}

/**
 * Assert that an element is visible with proper waiting
 */
function assertElementVisible(Browser $browser, string $selector): Browser
{
    return $browser->assertVisible($selector);
}

/**
 * Wait for a redirect and verify the URL path
 */
function assertRedirectedTo(Browser $browser, string $path): Browser
{
    return $browser->assertUrlPath($path);
}

/**
 * Fill and submit a form
 */
function fillForm(Browser $browser, array $fields, string $submitSelector = 'button[type="submit"]'): Browser
{
    foreach ($fields as $name => $value) {
        $browser->type($name, $value);
    }

    return $browser->click($submitSelector);
}

/**
 * Assert that multiple elements exist on the page
 */
function assertAllExist(Browser $browser, array $selectors): Browser
{
    foreach ($selectors as $selector) {
        $browser->assertVisible($selector);
    }

    return $browser;
}
