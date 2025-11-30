<?php

namespace App\Providers;

use App\Services\Pipeline\ProcessorRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting for API routes.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(100)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });

        RateLimiter::for('api-uploads', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }
}
