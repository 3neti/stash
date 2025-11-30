<?php

use Laravel\Dusk\Browser;

test('campaigns page redirects unauthenticated users to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/campaigns')
            ->assertPathIs('/login');
    });
});

test('campaigns create page redirects unauthenticated users to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/campaigns/create')
            ->assertPathIs('/login');
    });
});
