# Pest Browser Tests

This directory contains end-to-end browser tests using **Pest v4 Browser Tests** powered by **Playwright**.

## Environment Setup

**Local Development Environment**: Laravel Herd
- Project URL: `http://stash.test`
- Test Environment: `.env.testing` configured with `APP_URL=http://stash.test`
- Playwright manages browser binaries automatically

## Overview

Browser tests simulate real user interactions in the browser:
- Navigate pages
- Fill forms
- Click buttons
- Assert content visibility
- Test multi-step workflows

### Why Pest Browser Tests?

- **Multi-browser**: Chrome, Firefox, Safari, Edge, WebKit
- **Faster than Dusk**: Uses Playwright (more reliable waits)
- **Visual regression**: Built-in screenshot comparison
- **Better parallelization**: Run tests in parallel safely
- **Familiar API**: Similar to Dusk but simpler syntax
- **Zero dependencies**: Playwright binaries managed automatically

## Structure

```
tests/Browser/
├── README.md (this file)
├── BrowserTestCase.php (base test class)
├── Helpers.php (helper functions)
├── Auth/
│   └── LoginTest.php (authentication flows)
├── Dashboard/
│   └── DashboardTest.php (dashboard page tests)
├── Campaigns/
│   └── CampaignTest.php (campaign CRUD tests)
└── Documents/
    └── DocumentTest.php (document viewing tests)
```

## Running Tests

### Run all browser tests
```bash
php artisan pest --testsuite=Browser
```

### Run specific test file
```bash
php artisan pest tests/Browser/Auth/LoginTest.php
```

### Run tests by group
```bash
php artisan pest --testsuite=Browser --filter=auth
php artisan pest --testsuite=Browser --filter=campaigns
php artisan pest --testsuite=Browser --filter=dashboard
php artisan pest --testsuite=Browser --filter=documents
```

### Run with specific browser
```bash
# Default: Chromium
PLAYWRIGHT_BROWSER=chromium php artisan pest --testsuite=Browser

# Firefox
PLAYWRIGHT_BROWSER=firefox php artisan pest --testsuite=Browser

# WebKit (Safari)
PLAYWRIGHT_BROWSER=webkit php artisan pest --testsuite=Browser
```

### Run tests with headed browser (see execution)
```bash
PLAYWRIGHT_HEADED=1 php artisan pest --testsuite=Browser
```

### Run tests in parallel
```bash
php artisan pest --testsuite=Browser --parallel
```

### Debug mode with slow motion
```bash
PLAYWRIGHT_DEBUG=1 PLAYWRIGHT_SLOMO=1000 php artisan pest --testsuite=Browser
```

## Writing Tests

### Basic Structure

```php
<?php

test('user can log in', function () {
    visit('/login')
        ->type('email', 'user@example.com')
        ->type('password', 'password')
        ->click('button[type="submit"]')
        ->assertUrlPath('/dashboard');
})->group('auth');
```

### Key Methods

#### Navigation
```php
visit('/path')                      // Navigate to URL
->back()                           // Go back
->refresh()                        // Refresh page
```

#### Interaction
```php
->type('field_name', 'value')     // Type text into input
->type('email', 'test@test.com')
->clear('field_name')              // Clear input
->click('button_selector')         // Click element
->select('field', 'option_value')  // Select from dropdown
->check('checkbox_name')           // Check checkbox
->uncheck('checkbox_name')         // Uncheck checkbox
->press('Enter')                   // Press key
```

#### Waiting (automatic)
```php
->waitFor('selector')              // Wait for element
->waitForNavigation()              // Wait for page load
->wait(1000)                       // Wait milliseconds (use sparingly)
```

#### Assertions
```php
->assertUrlPath('/dashboard')      // Assert URL path
->assertSee('text')                // Assert text visible
->assertDontSee('text')            // Assert text not visible
->assertVisible('selector')        // Assert element visible
->assertNotVisible('selector')     // Assert element hidden
->assertCount(3, 'selector')       // Assert element count
->assertExists('selector')         // Assert element exists
->assertHasAttribute('selector', 'attribute')
->assertAttributeContains('selector', 'data-value', 'expected')
```

### Using Helper Functions

#### Login a user
```php
use App\Models\User;

test('authenticated user sees dashboard', function () {
    $user = User::factory()->create();

    loginAsUser($user)              // Helper from Helpers.php
        ->visit('/dashboard')
        ->assertSee('Dashboard');
});
```

#### Create and login in one call
```php
test('new user workflow', function () {
    loginTestUser(['role' => 'admin'])
        ->visit('/dashboard')
        ->assertSee('Admin Dashboard');
});
```

#### Fill multiple form fields
```php
test('create campaign', function () {
    loginTestUser()
        ->visit('/campaigns/create')
        ->type('name', 'My Campaign')
        ->type('description', 'Description')
        ->click('button[type="submit"]')
        ->assertUrlPath('/campaigns/1');
});
```

## Test Data

Use Laravel factories for test data:

```php
use App\Models\Campaign;
use App\Models\User;

test('user can view campaigns', function () {
    $user = User::factory()->create();
    Campaign::factory()->count(3)->create();

    loginAsUser($user)
        ->visit('/campaigns')
        ->assertSee('Campaigns');
});
```

## Best Practices

### 1. Use Data Attributes for Selectors
Add `data-testid` to HTML elements for stable selectors:

```vue
<!-- Vue component -->
<button data-testid="create-button">Create</button>
<div data-testid="processing-status">{{ status }}</div>
```

```php
// Test
->click('[data-testid="create-button"]')
->assertSee('[data-testid="processing-status"]')
```

### 2. One User Intent Per Test
Each test should verify one specific user action:

```php
// Good: Single intent
test('user can create campaign', function () {
    loginTestUser()
        ->visit('/campaigns/create')
        ->type('name', 'New Campaign')
        ->click('button[type="submit"]')
        ->assertUrlPath('/campaigns/1');
});

// Bad: Multiple intents in one test
test('user workflow', function () {
    // ... create campaign ...
    // ... edit campaign ...
    // ... delete campaign ...
});
```

### 3. Use Groups for Organization
```php
test('user can log in', function () {
    // ...
})->group('auth');

test('user can log out', function () {
    // ...
})->group('auth');
```

### 4. Avoid Hard Waits
Use Playwright's built-in waiting instead:

```php
// Bad
->wait(2000)

// Good: Playwright waits automatically
->click('button')
->assertSee('Success message')
```

### 5. Test Complete Workflows
Include multi-step user journeys:

```php
test('campaign creation workflow', function () {
    loginTestUser()
        ->visit('/campaigns')
        ->click('a[href="/campaigns/create"]')     // Navigate
        ->type('name', 'New Campaign')              // Fill form
        ->click('button[type="submit"]')            // Submit
        ->assertUrlPath('/campaigns/1')             // Verify redirect
        ->assertSee('New Campaign')                 // Verify content
        ->click('[data-testid="edit-button"]')     // Navigate to edit
        ->clear('name')
        ->type('name', 'Updated Campaign')
        ->click('button[type="submit"]')
        ->assertSee('Updated Campaign');            // Verify update
});
```

## Screenshots

Pest Browser Tests automatically takes screenshots on failure in `tests/Browser/screenshots/`.

### Manual Screenshots
```php
->screenshot('campaign-created')
```

## Debugging

### Print page source
```php
->dd()                              // Dump and die
->dumpPage()                        // Print HTML
```

### Interactive mode
```bash
PLAYWRIGHT_DEBUG=1 php artisan pest --testsuite=Browser --filter=auth
```

### Browser DevTools
```bash
PLAYWRIGHT_HEADED=1 PLAYWRIGHT_SLOMO=1000 php artisan pest --testsuite=Browser
```

## Database Isolation

All browser tests use `DatabaseMigrations` trait to ensure clean database for each test:

```php
// Automatic per test
// - Migrations run before test
// - Rollback after test
// - Each test starts with clean state
```

## CI/CD Integration

### GitHub Actions Example
```yaml
- name: Run browser tests
  env:
    PLAYWRIGHT_BROWSER: chromium
  run: php artisan pest --testsuite=Browser
```

### Local Pre-commit
```bash
php artisan pest --testsuite=Browser --filter=auth
```

## Coverage

Generate coverage reports:
```bash
php artisan pest --testsuite=Browser --coverage
```

## Environment Variables

Create `.env.testing` for test-specific configuration:

```env
PLAYWRIGHT_BROWSER=chromium
PLAYWRIGHT_HEADED=0
PLAYWRIGHT_DEBUG=0
PLAYWRIGHT_SLOMO=0
```

## Common Issues

### Tests hanging
- Check for missing waits
- Use `assertVisible()` before interacting with elements
- Ensure form submissions are properly waited

### Flaky tests
- Use proper selectors (data-testid > CSS classes)
- Avoid timing-dependent assertions
- Let Playwright wait automatically

### Database errors
- Ensure migrations are correct
- Check foreign key constraints
- Use factories properly

## Resources

- [Pest Documentation](https://pestphp.com)
- [Pest Browser Tests](https://pestphp.com/docs/browser-testing)
- [Playwright Documentation](https://playwright.dev)
- [Laravel Testing](https://laravel.com/docs/testing)

## Next Steps

1. Add `data-testid` attributes to Vue components
2. Run tests: `php artisan pest --testsuite=Browser`
3. Fix failing tests
4. Expand coverage for new features
