# Pest 4 Browser Tests Setup - Checklist

## ✅ Setup Completed

- [x] Installed `pestphp/pest-plugin-browser` (v4.1.1)
- [x] Created `pest.php` configuration
- [x] Created `tests/Browser/BrowserTestCase.php` (base class)
- [x] Created `tests/Browser/Helpers.php` (helper functions)
- [x] Created 21 browser tests across 4 groups
  - [x] `tests/Browser/Auth/LoginTest.php` (4 tests)
  - [x] `tests/Browser/Dashboard/DashboardTest.php` (5 tests)
  - [x] `tests/Browser/Campaigns/CampaignTest.php` (6 tests)
  - [x] `tests/Browser/Documents/DocumentTest.php` (6 tests)
- [x] Created comprehensive documentation
  - [x] `tests/Browser/README.md` (379 lines)
  - [x] `tests/Browser/COMPONENT_INTEGRATION.md` (422 lines)
  - [x] `BROWSER_TESTS_SETUP.md`
  - [x] `PEST_BROWSER_TESTS_SUMMARY.txt`
- [x] Updated `phpunit.xml` with Browser testsuite
- [x] Created test directories with `.gitkeep` files

## 🎯 Next Steps (Your Turn)

### Phase 1: Add data-testid Attributes (~1-2 hours)

Follow `tests/Browser/COMPONENT_INTEGRATION.md` examples and add `data-testid` attributes to:

- [ ] `resources/js/pages/Dashboard.vue`
- [ ] `resources/js/pages/campaigns/Index.vue`
- [ ] `resources/js/pages/campaigns/Create.vue`
- [ ] `resources/js/pages/campaigns/Edit.vue`
- [ ] `resources/js/pages/campaigns/Show.vue`
- [ ] `resources/js/pages/documents/Index.vue`
- [ ] `resources/js/pages/documents/Show.vue`
- [ ] `resources/js/components/CampaignCard.vue`
- [ ] `resources/js/components/DocumentUploader.vue`
- [ ] `resources/js/components/ProcessingStatusBadge.vue`
- [ ] `resources/js/components/StatsCard.vue`
- [ ] Other key components (NavUser, NavMain, Breadcrumbs, etc.)

### Phase 2: Run and Fix Tests (~1-2 hours)

- [ ] Run: `php artisan pest --testsuite=Browser`
- [ ] Fix failing tests by adding missing `data-testid` attributes
- [ ] Verify all tests pass: `php artisan pest --testsuite=Browser`
- [ ] Test with headed browser: `PLAYWRIGHT_HEADED=1 php artisan pest --testsuite=Browser`

### Phase 3: Expand Coverage (As needed)

- [ ] Add browser tests for new features
- [ ] Use existing tests as templates
- [ ] Follow group naming convention (auth, dashboard, campaigns, documents)
- [ ] Keep tests focused on user intents

### Phase 4: CI/CD Integration (Optional)

- [ ] Add to GitHub Actions workflow
- [ ] Configure parallel execution
- [ ] Set up screenshot artifacts

## 📋 Quick Reference Commands

```bash
# Run all browser tests
php artisan pest --testsuite=Browser

# Run specific group
php artisan pest --testsuite=Browser --filter=auth
php artisan pest --testsuite=Browser --filter=campaigns
php artisan pest --testsuite=Browser --filter=dashboard
php artisan pest --testsuite=Browser --filter=documents

# Watch with visible browser
PLAYWRIGHT_HEADED=1 php artisan pest --testsuite=Browser

# Debug mode
PLAYWRIGHT_DEBUG=1 PLAYWRIGHT_SLOMO=1000 php artisan pest --testsuite=Browser

# Different browsers
PLAYWRIGHT_BROWSER=firefox php artisan pest --testsuite=Browser
PLAYWRIGHT_BROWSER=webkit php artisan pest --testsuite=Browser

# Parallel execution
php artisan pest --testsuite=Browser --parallel
```

## 📚 Documentation

Read these in order:

1. **PEST_BROWSER_TESTS_SUMMARY.txt** - Overview (this is what we created)
2. **BROWSER_TESTS_SETUP.md** - Detailed setup and next steps
3. **tests/Browser/README.md** - Complete testing guide
4. **tests/Browser/COMPONENT_INTEGRATION.md** - Examples for each component

## 🔍 Expected Test Results

When you first run tests, you'll see failures like:

```
FAILED  tests/Browser/Dashboard/DashboardTest.php
  Tests\Browser\Dashboard\DashboardTest > authenticated user can view dashboard
  Element '[data-testid="total-campaigns-stat"]' not found
```

This is **expected** and normal! It means:
1. Tests are working correctly
2. The Vue components don't have `data-testid` attributes yet
3. You need to add the attributes (see COMPONENT_INTEGRATION.md)

## ✨ Key Features

- ✅ Multi-browser testing (Chrome, Firefox, Safari)
- ✅ Fast execution (Playwright)
- ✅ Auto-screenshots on failure
- ✅ Database isolation per test
- ✅ Helper functions for common operations
- ✅ CI/CD ready
- ✅ 21 starter tests
- ✅ Comprehensive documentation

## 💡 Tips

1. **Start Small** - Begin with one component (Dashboard)
2. **Add Attributes** - Follow COMPONENT_INTEGRATION.md patterns
3. **Run Often** - Test after each component update
4. **Use Helpers** - `loginTestUser()`, `fillForm()`, etc.
5. **Debug Visually** - Use `PLAYWRIGHT_HEADED=1` to watch tests
6. **Group Related Tests** - Use `->group('category')`

## 🚀 Success Criteria

Once complete, you should have:

- [ ] All 21 browser tests passing
- [ ] Data attributes on all interactive components
- [ ] Tests covering all major user workflows
- [ ] Ability to run tests in multiple browsers
- [ ] CI/CD integration ready
- [ ] Team confidence in UI/UX changes

## 📞 Support

- **Playwright Docs**: https://playwright.dev
- **Pest Docs**: https://pestphp.com
- **Laravel Testing**: https://laravel.com/docs/testing

## Timeline Estimate

- **Phase 1 (Add data-testid)**: 1-2 hours
- **Phase 2 (Fix tests)**: 1-2 hours
- **Phase 3 (Expand coverage)**: Ongoing as needed
- **Phase 4 (CI/CD)**: 30 minutes

**Total to get all tests passing**: 2-4 hours

---

## Summary

You now have a **complete end-to-end browser testing setup** ready to use!

Next step: Open `tests/Browser/COMPONENT_INTEGRATION.md` and start adding `data-testid` attributes to your Vue components. 🎯
