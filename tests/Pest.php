<?php

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

// Auth and Settings tests use standard RefreshDatabase (non-tenant)
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature/Auth')
    ->in('Feature/Settings')
    ->in('Feature/ExampleTest.php');

// Tenant-aware Feature tests
uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class)
    ->in('Feature/Campaign')
    ->in('Feature/DeadDrop')
    ->in('Feature/Documents')
    ->in('Feature/StateMachine')
    ->in('Feature/Workflows')
    ->in('Feature/Smoke');

// Top-level Feature tests (use tenant setup to be safe)
uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class)
    ->in('Feature/Phase12IntegrationTest.php')
    ->in('Feature/TenancyTest.php')
    ->in('Feature/DashboardTest.php')
    ->in('Feature/SetupVerificationTest.php');

// Integration tests also use tenant database setup
uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class)->in('Integration');

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
