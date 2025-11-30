<?php

use Laravel\Dusk\Browser;

test('login page displays form fields', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertVisible('input[type="email"]')
            ->assertVisible('input[type="password"]')
            ->assertSee('Log in');
    });
});

test('unauthenticated user is redirected from dashboard', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->assertPathIs('/login');
    });
});

test('register page displays form fields', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/register')
            ->assertVisible('input[type="text"]')
            ->assertVisible('input[type="email"]')
            ->assertVisible('input[type="password"]');
    });
});
