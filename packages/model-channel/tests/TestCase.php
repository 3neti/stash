<?php

namespace LBHurtado\ModelChannel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as BaseTestCase;
use LBHurtado\ModelChannel\Tests\Models\User;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginTestUser(); // Log in a test user for all tests
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'LBHurtado\\ModelChannel\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Load configuration files
        $this->loadConfig();
    }

    protected function getPackageProviders($app)
    {
        return [
        \LBHurtado\ModelChannel\ModelChannelServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Optional: Set web guard as the default
        $app['config']->set('auth.defaults.guard', 'web');

        // Run the migration from the local package
        $userMigration = include __DIR__ . '/../database/migrations/test/0001_01_01_000000_create_users_table.php';
        $userMigration->up();
        $channelMigration = include __DIR__ . '/../database/migrations/2024_08_02_000000_create_channels_table.php';
        $channelMigration->up();
    }

    // Define a reusable method for logging in a user
    protected function loginTestUser()
    {
        $user = new User([
            'id' => 1, // Unique ID for the user
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        $user->save();
        $this->actingAs($user); // Simulate authentication as this user
    }

    /**
     * Load the package configuration files.
     */
    protected function loadConfig()
    {
        $this->app['config']->set(
            'model-channel',
            require __DIR__ . '/../config/model-channel.php'
        );
    }
}
