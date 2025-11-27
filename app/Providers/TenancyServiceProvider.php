<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\TenantCreateCommand;
use App\Console\Commands\TenantDeleteCommand;
use App\Console\Commands\TenantListCommand;
use App\Console\Commands\TenantMigrateCommand;
use App\Http\Middleware\InitializeTenancy;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for custom multi-database tenancy.
 */
class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register tenancy services as singletons
        $this->app->singleton(TenantConnectionManager::class);
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register middleware
        $this->app['router']->aliasMiddleware('tenant', InitializeTenancy::class);

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TenantCreateCommand::class,
                TenantMigrateCommand::class,
                TenantListCommand::class,
                TenantDeleteCommand::class,
            ]);
        }
    }
}
