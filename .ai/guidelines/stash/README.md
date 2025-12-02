# Stash/DeadDrop AI Guidelines

👉 **New to this project? Start with [QUICKSTART.md](QUICKSTART.md) for essential knowledge in 5 minutes.**

Welcome to the comprehensive AI development guidelines for Stash. This directory contains everything an AI agent needs to understand the application's architecture, workflows, testing patterns, and debugging procedures.

## ✅ Current Project Status (December 2024)

**Production Ready**: Core workflow system migrated to Laravel Workflow

### What's Different Now
- **Workflow System**: Migrated from legacy pipeline to Laravel Workflow (Phase 5-6 COMPLETE)
  - Removed: `ProcessDocumentJob`, `SetTenantContext` middleware
  - Added: `DocumentProcessingWorkflow` with 4 Activities
  - Code reduction: 56% (873→380 lines)
  - Monitoring: Waterline + Horizon installed

- **State Management**: Now uses `spatie/laravel-model-states`
  - Campaign states: `ActiveCampaignState`, `DraftCampaignState`, `PausedCampaignState`, `ArchivedCampaignState`
  - Document states: `CompletedDocumentState`, `ProcessingDocumentState`, `FailedDocumentState`
  - ⚠️ **NEVER use 'status' strings** - always use state classes

- **Test Suite**: 364/428 passing (85%)
  - Core workflow: 16/16 (100%)
  - Document upload: 19/19 (100%)
  - Remaining: Peripheral features (fix incrementally)
  - See: `TEST_FIX_SUMMARY.md` for details

- **Multi-Tenancy Pattern**: 
  - Central DB: `tenants`, `users`
  - Tenant DBs: `campaigns`, `documents`, `document_jobs`
  - **Always wrap tenant operations**: `TenantContext::run($tenant, fn() => ...)`
  - **Tests must use**: `UsesDashboardSetup` trait + `TenantContext::run()`

### Key Documents for New Sessions
1. **DEPLOYMENT_READY.md** - Production deployment guide and system status
2. **TEST_FIX_SUMMARY.md** - Test status and fix patterns
3. **LARAVEL_WORKFLOW_ARCHITECTURE.md** - Workflow system architecture
4. **WARP.md** - Project conventions (see "Project Status" section at top)

## Quick Navigation

### 🏗️ Architecture & Domain
- **[domain.md](domain.md)** - Complete Stash/DeadDrop platform architecture
  - Multi-tenancy patterns, campaign system, processor framework
  - Credential vault, multi-AI routing, queue abstraction
  - Document lifecycle, storage patterns, stashlets

### 🧪 Testing & Quality
- **[testing.md](testing.md)** - Comprehensive testing patterns and strategies
  - Pest v4 usage, factory patterns, mocking strategies
  - Multi-tenant testing, browser testing with Dusk
  - Coverage requirements and CI/CD strategy

### 🐛 Debugging & Problem-Solving
- **[tdd-tenancy-workflow.md](tdd-tenancy-workflow.md)** - 4-phase TDD workflow for multi-tenant bugs
  - When to use: Database connection errors, SQLSTATE issues, table not found
  - Phase 1: Write failing Feature tests in `tests/Feature/DeadDrop/`
  - Phase 2: Debug using investigation checklist
  - Phase 3: Implement fix (minimal or auto-provision approach)
  - Phase 4: Verify with Dusk browser tests
  - Best practices, common issues, troubleshooting guide

### 🤖 AI Evolution System
- **[meta-campaign.md](meta-campaign.md)** - Self-evolution AI system documentation
  - Intent classification and code planning
  - Patch generation and validation
  - CI orchestration and PR creation
  - Safety guardrails and monitoring

---

## When to Use Each Guide

| Situation | Reference |
|-----------|-----------|
| **Building a new feature** | Start with `domain.md` to understand context |
| **Adding tests** | See `testing.md` for patterns and best practices |
| **Debugging a live browser bug** | Follow `tdd-tenancy-workflow.md` (4-phase TDD process) |
| **Implementing AI evolution** | Refer to `meta-campaign.md` |
| **Multi-tenant connection error** | Go directly to `tdd-tenancy-workflow.md` Phase 2 troubleshooting |

---

## Key Principles

### 1. Always Write Tests First (TDD)
When you encounter a bug:
- ❌ Don't debug randomly
- ❌ Don't modify code without tests
- ✅ Write failing test first
- ✅ Understand root cause in Phase 2
- ✅ Implement fix
- ✅ Verify with Dusk browser test

### 2. Use Correct Test Location for Multi-Tenant Issues
- ✅ Create tests in `tests/Feature/DeadDrop/` for tenant-related issues
- ✅ Use `DeadDropTestCase` as base class
- ✅ Use `TenantContext::run()` to initialize context
- ❌ Don't use generic `tests/Feature/` for tenant bugs

### 3. Run Full Test Suite After Each Change
```bash
# After implementing any fix
php artisan test

# Should see same or more tests passing (never fewer)
```

### 4. Handle PostgreSQL DDL Carefully
PostgreSQL doesn't allow DDL (CREATE DATABASE, DROP DATABASE) inside transactions:
```php
// ✅ Correct for PostgreSQL DDL
if ($pdo->inTransaction()) {
    $pdo->commit();
}
$pdo->exec('CREATE DATABASE "name"');

// ❌ Wrong - will throw "cannot run inside a transaction block"
DB::connection('pgsql')->statement('CREATE DATABASE ...');
```

### 5. Document Your Findings
When fixing a bug:
- Create Phase 1 test file with clear scenario
- Document root cause in commit message
- Reference relevant guideline in code comments
- Update this README if new pattern emerges

---

## Best Practices Checklist

### For Feature Implementation
- [ ] Read relevant domain.md section first
- [ ] Check if similar feature exists (don't duplicate)
- [ ] Use Laravel Boost `search-docs` for framework features
- [ ] Run `php artisan test` after changes
- [ ] Commit with clear message referencing architecture

### For Bug Fixes
- [ ] Follow `tdd-tenancy-workflow.md` 4-phase process
- [ ] Create test in `tests/Feature/DeadDrop/` if multi-tenant
- [ ] Run full test suite to verify no regressions
- [ ] Commit each phase separately
- [ ] Update guideline if new pattern discovered

### For Testing
- [ ] Use `DeadDropTestCase` for multi-tenant tests
- [ ] Use factories for test data (see `testing.md`)
- [ ] Mock external services (AI providers, queues)
- [ ] Test happy path + error scenarios
- [ ] Aim for 80%+ coverage

---

## Related Documentation

- **WARP.md** - Project conventions and setup instructions
- **TDD Workflow** - See `tdd-tenancy-workflow.md` for detailed 4-phase process
- **Laravel Boost** - Available MCP tools: `search-docs`, `tinker`, `database-query`, etc.

---

## For Specific Issues

### "SQLSTATE[42P01]: Undefined table"
→ See `tdd-tenancy-workflow.md` Phase 2: "Check TenantContext behavior"

### "database ... does not exist"  
→ See `tdd-tenancy-workflow.md` Phase 2: "Decision Point" → Category: "Database doesn't exist"

### "CREATE DATABASE cannot run inside a transaction block"
→ See `tdd-tenancy-workflow.md` Troubleshooting: "PostgreSQL specifics"

### "Method does not exist" errors
→ See `tdd-tenancy-workflow.md` Troubleshooting: "Check Laravel version APIs"

### New test passes but existing tests break
→ See `tdd-tenancy-workflow.md` Phase 3: Implementation patterns (Option 1 vs Option 2)

---

## Updating Guidelines

When you discover a new pattern or pattern-specific workflow:
1. Document it in relevant guide
2. Add example/real-world scenario
3. Commit with message referencing the change
4. Update this README if it adds a new section

---

## Version History

- **v1.0** (2025-11-30) - Initial comprehensive guidelines
  - Added TDD workflow for multi-tenant debugging
  - Documented 4-phase process with real example (campaign detail route)
  - Added best practices, common issues, and troubleshooting

---

## Questions?

Refer to the specific guide that matches your situation. Each guide is designed to be self-contained while cross-referencing related topics.

**Most Common Entry Point**: 👉 Start with `tdd-tenancy-workflow.md` if you're debugging a bug
