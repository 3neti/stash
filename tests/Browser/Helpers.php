<?php

declare(strict_types=1);

use Pest\Testing\Browser;

/**
 * Browser test helpers - pure HTTP operations that don't depend on Laravel container
 */

/**
 * Assert that an element is visible with proper waiting
 */
function assertElementVisible(Browser $browser, string $selector): Browser
{
    return $browser->assertVisible($selector);
}

/**
 * Wait for a redirect and verify the URL path
 */
function assertRedirectedTo(Browser $browser, string $path): Browser
{
    return $browser->assertUrlPath($path);
}

/**
 * Fill and submit a form
 */
function fillForm(Browser $browser, array $fields, string $submitSelector = 'button[type="submit"]'): Browser
{
    foreach ($fields as $name => $value) {
        $browser->type($name, $value);
    }

    return $browser->click($submitSelector);
}

/**
 * Assert that multiple elements exist on the page
 */
function assertAllExist(Browser $browser, array $selectors): Browser
{
    foreach ($selectors as $selector) {
        $browser->assertVisible($selector);
    }

    return $browser;
}
