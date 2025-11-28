<?php

namespace App\Providers;

use App\Services\Pipeline\ProcessorRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ProcessorRegistry as singleton
        $this->app->singleton(ProcessorRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
