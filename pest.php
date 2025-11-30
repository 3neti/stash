<?php

declare(strict_types=1);

use Pest\Support\Str;
use Pest\Testing\TestClass;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions are always bound into a specific TestCase.
| This TestCase is a great place to hook into your tests. Here you may define helper
| methods or setup state that is shared amongst all your tests.
|
*/

uses(Tests\TestCase::class)->in('tests/Feature', 'tests/Unit');
uses(Tests\DuskTestCase::class)->in('tests/Browser');

// Dusk tests use proper Laravel Dusk framework - they work reliably with container bootstrap

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can call
| to assert that a value meets a given condition. By default we have some built-in types of
| expectations. However, you may add your own expectations using the "extend()" method.
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
| Pest allows you to define custom helper functions. This is perfect for helpers you use
| throughout your tests to build upon existing functionality or perform common tasks.
|
*/

function visitPage(string $path): \Pest\Testing\Browser
{
    return visit($path);
}

function seeText(string $text): bool
{
    return text($text)->exists();
}
