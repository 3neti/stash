<?php

use Laravel\Dusk\Browser;

test('documents page redirects unauthenticated users to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/documents')
            ->assertPathIs('/login');
    });
});

test('document detail page redirects unauthenticated users to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/documents/test-uuid')
            ->assertPathIs('/login');
    });
});
