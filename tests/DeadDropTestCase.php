<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case for DeadDrop package tests.
 *
 * Provides common setup, helpers, and patterns for testing
 * DeadDrop mono-repo packages.
 */
abstract class DeadDropTestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Additional DeadDrop-specific setup can go here
        // e.g., seeding common test data, configuring test credentials, etc.
    }

    /**
     * Get package-specific configuration.
     */
    protected function getPackageConfig(string $key, mixed $default = null): mixed
    {
        return config("dead-drop.{$key}", $default);
    }

    /**
     * Mock external services for DeadDrop packages.
     *
     * @param  array<string, mixed>  $mocks
     */
    protected function mockServices(array $mocks): void
    {
        foreach ($mocks as $abstract => $mock) {
            $this->app->instance($abstract, $mock);
        }
    }
}
