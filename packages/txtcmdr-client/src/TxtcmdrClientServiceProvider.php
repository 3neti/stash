<?php

namespace LBHurtado\DeadDrop\TxtcmdrClient;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use LBHurtado\DeadDrop\TxtcmdrClient\Channels\SmsChannel;
use LBHurtado\DeadDrop\TxtcmdrClient\Client\TxtcmdrClient;
use LBHurtado\DeadDrop\TxtcmdrClient\Client\TxtcmdrClientInterface;
use LBHurtado\DeadDrop\TxtcmdrClient\Drivers\TxtcmdrSmsDriver;

class TxtcmdrClientServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/txtcmdr-client.php',
            'txtcmdr-client'
        );

        $this->app->bind(TxtcmdrClientInterface::class, TxtcmdrClient::class);

        // Register txtcmdr SMS driver
        $this->app->singleton('sms.driver.txtcmdr', function ($app) {
            return new TxtcmdrSmsDriver(
                apiUrl: config('txtcmdr-client.default_url'),
                apiToken: config('txtcmdr-client.api_token', ''),
                defaultSenderId: config('txtcmdr-client.default_sender_id'),
                timeout: config('txtcmdr-client.timeout', 30),
                verifySSL: config('txtcmdr-client.verify_ssl', true)
            );
        });

        // Register SMS notification channel
        $this->app->singleton(SmsChannel::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/txtcmdr-client.php' => config_path('txtcmdr-client.php'),
            ], 'txtcmdr-client-config');
        }

        // Register 'sms' channel alias for SmsChannel
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('sms', function ($app) {
                return $app->make(SmsChannel::class);
            });
        });
    }
}
