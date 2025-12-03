<?php

namespace LBHurtado\Contact;

use Illuminate\Support\ServiceProvider;

class ContactServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/contact.php',
            'contact'
        );
    }

    public function boot(): void
    {
        // Don't auto-load migrations - Stash has its own tenant migrations
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/contact.php' => config_path('contact.php'),
        ], 'config');
    }
}
