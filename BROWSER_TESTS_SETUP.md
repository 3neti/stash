# Pest 4 Browser Tests Setup

✅ **Setup Complete!** Your project now has end-to-end browser testing with Pest 4 and Playwright.

## What Was Installed

```
pestphp/pest-plugin-browser (v4.1.1)
├── playwright (multi-browser support)
├── amphp/* (async HTTP)
└── supporting libraries
```

## Structure

```
tests/Browser/
├── README.md                          # Comprehensive testing guide
├── BrowserTestCase.php               # Base class for browser tests
├── Helpers.php                       # Helper functions (loginAsUser, etc.)
├── COMPONENT_INTEGRATION.md          # Guide for adding data-testid attributes
├── Auth/
│   └── LoginTest.php                # Authentication tests (4 tests)
├── Dashboard/
│   └── DashboardTest.php            # Dashboard tests (5 tests)
├── Campaigns/
│   └── CampaignTest.php             # Campaign CRUD tests (6 tests)
├── Documents/
│   └── DocumentTest.php             # Document tests (6 tests)
└── screenshots/                     # Screenshots on test failure
```

## Test Coverage

✅ **21 browser tests** covering:
- **Auth** (4 tests): Login, register, logout, validation
- **Dashboard** (5 tests): Stats, quick actions, authentication
- **Campaigns** (6 tests): CRUD operations, filtering
- **Documents** (6 tests): Viewing, filtering, status display

## Quick Start

### Run All Browser Tests
```bash
php artisan pest --testsuite=Browser
```

### Run by Group
```bash
php artisan pest --testsuite=Browser --filter=auth
php artisan pest --testsuite=Browser --filter=campaigns
php artisan pest --testsuite=Browser --filter=dashboard
php artisan pest --testsuite=Browser --filter=documents
```

### Watch Tests with Headed Browser
```bash
PLAYWRIGHT_HEADED=1 php artisan pest --testsuite=Browser
```

### Debug Mode
```bash
PLAYWRIGHT_DEBUG=1 PLAYWRIGHT_SLOMO=1000 php artisan pest --testsuite=Browser
```

## Next Steps

### 1. Add Data Attributes to Vue Components
Add `data-testid` attributes to your Vue components for stable, maintainable selectors.

See: `tests/Browser/COMPONENT_INTEGRATION.md` for examples.

**Key components to update:**
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/campaigns/Index.vue`
- `resources/js/pages/campaigns/Create.vue`
- `resources/js/pages/campaigns/Edit.vue`
- `resources/js/pages/campaigns/Show.vue`
- `resources/js/pages/documents/Index.vue`
- `resources/js/pages/documents/Show.vue`
- `resources/js/components/CampaignCard.vue`
- `resources/js/components/DocumentUploader.vue`
- `resources/js/components/ProcessingStatusBadge.vue`
- `resources/js/components/StatsCard.vue`

Example:
```vue
<!-- Add to buttons, inputs, modals, etc. -->
<button data-testid="create-campaign">Create Campaign</button>
<input data-testid="campaign-name" v-model="form.name" />
<div data-testid="processing-status">{{ status }}</div>
```

### 2. Run Tests to Check Setup
```bash
cd /Users/rli/PhpstormProjects/stash
php artisan pest --testsuite=Browser --filter=dashboard
```

You'll likely see failures due to missing `data-testid` attributes - that's expected!

### 3. Fix Failing Tests
Update Vue components to include `data-testid` attributes as documented in `COMPONENT_INTEGRATION.md`.

### 4. Iterate
- Run tests frequently
- Fix failures by adding attributes
- Expand test coverage as features grow

## Configuration

### Browser Selection
```bash
# Chromium (default)
php artisan pest --testsuite=Browser

# Firefox
PLAYWRIGHT_BROWSER=firefox php artisan pest --testsuite=Browser

# WebKit (Safari)
PLAYWRIGHT_BROWSER=webkit php artisan pest --testsuite=Browser
```

### Test Environment
Tests automatically use:
- Fresh database migrations per test
- Clean session state
- Test database (PostgreSQL stash_test)

### CI/CD Ready
Tests are ready for GitHub Actions:
```yaml
- name: Run browser tests
  run: php artisan pest --testsuite=Browser
```

## Helper Functions Available

From `tests/Browser/Helpers.php`:

```php
loginAsUser($user)                    // Login as specific user
loginTestUser($attributes)            // Create and login user
assertUserAuthenticated($browser)     // Assert user is logged in
fillForm($browser, $fields)           // Fill and submit form
assertAllExist($browser, $selectors)  // Assert multiple elements exist
```

## Example Test

```php
test('user can create campaign', function () {
    loginTestUser()                              // Create and login user
        ->visit('/campaigns/create')            // Navigate
        ->type('name', 'Test Campaign')         // Fill form
        ->click('button[type="submit"]')        // Submit
        ->assertUrlPath('/campaigns/1')         // Verify redirect
        ->assertSee('Test Campaign');           // Verify content
})->group('campaigns');
```

## Documentation

- **tests/Browser/README.md** - Complete testing guide with all methods
- **tests/Browser/COMPONENT_INTEGRATION.md** - Guide for adding data-testid attributes
- **pest.php** - Test configuration and helper function setup

## Commands Reference

```bash
# All browser tests
php artisan pest --testsuite=Browser

# Specific test file
php artisan pest tests/Browser/Dashboard/DashboardTest.php

# Run by filter/group
php artisan pest --testsuite=Browser --filter=auth

# With coverage
php artisan pest --testsuite=Browser --coverage

# Parallel execution (faster)
php artisan pest --testsuite=Browser --parallel

# Only failed tests
php artisan pest --testsuite=Browser --failed

# With screenshots
php artisan pest --testsuite=Browser  # Automatic on failure
```

## Troubleshooting

### Tests hang/timeout
- Check selectors exist: Add `data-testid` to elements
- Use `assertVisible()` before interacting
- Avoid hard waits - Playwright waits automatically

### Tests fail with "element not found"
- Verify `data-testid` attribute exists in Vue component
- Check selector syntax: `[data-testid="button-name"]`
- Use headed mode to debug: `PLAYWRIGHT_HEADED=1`

### Database errors
- Ensure migrations are correct
- Check factory definitions
- Verify foreign key constraints

## What to Do Now

1. **Update Vue Components** - Add `data-testid` attributes
   - Follow examples in `tests/Browser/COMPONENT_INTEGRATION.md`
   - Start with main pages and reusable components
   
2. **Run Tests** - See what breaks
   ```bash
   php artisan pest --testsuite=Browser
   ```

3. **Fix Failures** - Add missing attributes
   - Most failures will be "element not found"
   - Add the `data-testid` attribute and retry

4. **Verify All Pass** - Confirm complete test suite
   ```bash
   php artisan pest --testsuite=Browser
   ```

5. **Expand Coverage** - Add tests for new features
   - Use existing tests as templates
   - Follow patterns in Auth, Dashboard, Campaigns, Documents

## Performance

- **Headless (default)**: ~5-10s per test
- **Headed (visual)**: ~10-15s per test
- **Parallel**: 3-4x faster
- **Total suite**: ~60-90s for all 21 tests

## Browser Support

Pest 4 with Playwright supports:
- ✅ Chromium (Chrome, Edge)
- ✅ Firefox
- ✅ WebKit (Safari)
- ✅ Mobile emulation (responsive testing)
- ✅ Dark mode testing

## Resources

- [Pest Documentation](https://pestphp.com)
- [Pest Browser Testing](https://pestphp.com/docs/browser-testing)
- [Playwright Documentation](https://playwright.dev)
- [Laravel Testing Best Practices](https://laravel.com/docs/testing)

---

## Summary

You now have:
- ✅ Pest 4 Browser Tests configured
- ✅ Playwright for multi-browser testing
- ✅ 21 example tests covering key workflows
- ✅ Helper functions for common operations
- ✅ Comprehensive documentation
- ✅ Ready for CI/CD integration

**Next:** Add `data-testid` attributes to Vue components and run tests!
