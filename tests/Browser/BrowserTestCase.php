<?php

declare(strict_types=1);

namespace Tests\Browser;

use PHPUnit\Framework\TestCase;

/**
 * Browser Test Case
 * 
 * Pest browser tests connect via HTTP to the running Laravel application
 * so they do NOT need to manage database state like traditional tests.
 * The running dev server handles all application logic.
 * 
 * Environment: Laravel Herd at http://stash.test
 */
abstract class BrowserTestCase extends TestCase
{
    // Browser tests operate via HTTP requests to the running application
    // No database manipulation needed - the running server handles it
}
