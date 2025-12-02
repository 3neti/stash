<?php

namespace App\Providers;

use App\Listeners\WorkflowCompletedListener;
use App\Listeners\WorkflowFailedListener;
use App\Services\Pipeline\Hooks\TimeTrackingHook;
use App\Services\Pipeline\ProcessorHookManager;
use App\Services\Pipeline\ProcessorRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Workflow\Events\WorkflowCompleted;
use Workflow\Events\WorkflowFailed;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register ProcessorRegistry as singleton
        $this->app->singleton(ProcessorRegistry::class);

        // Register ProcessorHookManager as singleton
        $this->app->singleton(ProcessorHookManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-discover and register processors from filesystem
        $registry = $this->app->make(ProcessorRegistry::class);
        $registry->discover();

        // Also register processors from database (tenant-aware)
        // This runs after discover() so database slugs take precedence
        $registry->registerFromDatabase();

        // Register processor hooks
        $hookManager = $this->app->make(ProcessorHookManager::class);
        $hookManager->register(new TimeTrackingHook);

        // Register workflow event listeners
        Event::listen(WorkflowCompleted::class, WorkflowCompletedListener::class);
        Event::listen(WorkflowFailed::class, WorkflowFailedListener::class);

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
