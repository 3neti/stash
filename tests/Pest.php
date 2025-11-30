<?php

uses(
    Tests\DuskTestCase::class,
    // Illuminate\Foundation\Testing\DatabaseMigrations::class,
)->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// DeadDrop Package Tests (tenant-aware with dual DB setup)
uses(Tests\DeadDropTestCase::class)
    ->in('Feature/DeadDrop', 'Unit/DeadDrop', 'Integration');

// Laravel Feature Tests (Auth, Settings, etc.) - use standard TestCase with RefreshDatabase
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature/Auth', 'Feature/Settings');

// API tests - use DeadDropTestCase for tenant-aware testing
uses(Tests\DeadDropTestCase::class)
    ->in('Feature/Api');

// Top-level Feature tests - standard TestCase with RefreshDatabase
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature/*.php');

// State Machine tests - use DeadDropTestCase
uses(Tests\DeadDropTestCase::class)
    ->in('Feature/StateMachine');

// Top-level Unit tests - use DeadDropTestCase (for state tests)
uses(Tests\DeadDropTestCase::class)
    ->in('Unit/*.php');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
