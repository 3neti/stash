<?php

namespace LBHurtado\ModelChannel;

use Illuminate\Support\ServiceProvider;

class ModelChannelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/model-channel.php',
            'model-channel'
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Allow publishing the configuration files
        $this->publishes([
            __DIR__ . '/../config/model-channel.php' => config_path('model-channel.php'),
        ], 'config');
    }
}
