<?php

use Laravel\Dusk\Browser;

test('unauthenticated user is redirected from dashboard', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->assertPathIs('/login');
    });
});

test('login page loads successfully', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertSee('Log in');
    });
});
