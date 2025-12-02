# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Status (December 2024)

**Production Status**: ✅ READY - Core workflow system fully migrated and tested

### Recent Changes
- **Laravel Workflow Migration**: Phase 5-6 COMPLETE
  - Migrated from legacy pipeline to Laravel Workflow (56% code reduction)
  - Removed: ProcessDocumentJob, SetTenantContext middleware (~873 lines)
  - Added: DocumentProcessingWorkflow + 4 Activities (~380 lines)
  - Monitoring: Waterline (workflows) + Horizon (queues) installed
  
- **Test Suite**: 364/428 passing (85%)
  - Core workflow tests: 16/16 (100%)
  - Document upload tests: 19/19 (100%)
  - Remaining failures: Peripheral features (fix incrementally)
  - See: `TEST_FIX_SUMMARY.md` for details

- **State Management**: Migrated to spatie/laravel-model-states
  - Campaign: Uses ActiveCampaignState, DraftCampaignState, etc.
  - Document: Uses CompletedDocumentState, ProcessingDocumentState, etc.
  - ⚠️ Use state classes, NOT 'status' strings

- **Multi-Tenancy**: Fully implemented
  - Central DB: tenants, users
  - Tenant DBs: campaigns, documents, jobs
  - Pattern: Wrap tenant operations in `TenantContext::run($tenant, fn() => ...)`

### Quick References
- **Deployment Guide**: `DEPLOYMENT_READY.md`
- **Test Status**: `TEST_FIX_SUMMARY.md`  
- **Workflow Architecture**: `LARAVEL_WORKFLOW_ARCHITECTURE.md`
- **Monitoring**: `/waterline` (workflows), `/horizon` (queues)

## Technology Stack

This is a **Laravel 12 + Inertia.js v2 + Vue 3 + Tailwind CSS v4** starter kit with authentication.

### Core Dependencies
- **Backend**: Laravel 12 with PHP 8.2+
- **Frontend**: Vue 3, Inertia.js v2, Tailwind CSS v4
- **Auth**: Laravel Fortify with 2FA support
- **Testing**: Pest v4
- **Code Quality**: Laravel Pint (formatting), ESLint + Prettier (JS/TS)
- **Build Tools**: Vite, Wayfinder (TypeScript route generation)
- **Dev Tools**: Laravel Boost (AI assistance), Laravel Pail (log tailing), Laravel Sail (Docker)

## Local Development Setup

### Hostname Configuration
The application uses `stash.test` as the local hostname. Ensure it's in your `/etc/hosts`:
```
127.0.0.1 stash.test
```

Then access the application at: `http://stash.test:8000`

## Common Development Commands

### Initial Setup
```bash
composer run setup  # Install dependencies, copy .env, generate key, migrate, build assets
```

### Development Server
```bash
composer run dev    # Start all services: Laravel server, queue worker, logs, and Vite
# This runs 4 concurrent processes:
# - php artisan serve (port 8000)
# - php artisan queue:listen
# - php artisan pail (log tailing)
# - npm run dev (Vite HMR)
```

### SSR Development Mode
```bash
composer run dev:ssr  # For server-side rendering with Inertia.js SSR
```

### Testing
```bash
composer run test          # Run all tests via Pest
php artisan test           # Alternative direct command
php artisan test --filter  # Run specific tests by name/filter
php artisan test tests/Feature/ExampleTest.php  # Run specific file
```

### Code Quality

#### PHP Formatting
```bash
./vendor/bin/pint        # Format all PHP files with Laravel Pint
./vendor/bin/pint --test # Check formatting without making changes
```

#### JavaScript/TypeScript
```bash
npm run lint           # Run ESLint with auto-fix
npm run format         # Format JS/TS/Vue files with Prettier
npm run format:check   # Check formatting without making changes
```

### Building Assets
```bash
npm run dev      # Start Vite dev server with HMR
npm run build    # Production build
npm run build:ssr # Production build with SSR support
```

### Database
```bash
php artisan migrate               # Run migrations
php artisan migrate:fresh --seed  # Fresh migration with seeders
php artisan db:seed               # Run seeders only
```

### Routes
```bash
php artisan route:list            # List all registered routes
php artisan wayfinder:generate    # Generate TypeScript route helpers
```

### Wayfinder (Type-Safe Routes)
Wayfinder automatically generates TypeScript route helpers. Import routes in Vue components:

```typescript
import { dashboard, login, register } from '@/routes';

// In template or script:
<Link :href="dashboard()">Dashboard</Link>
```

Routes are re-generated automatically during development. Manually regenerate with:
```bash
php artisan wayfinder:generate
```

### Artisan Commands
```bash
php artisan list              # List all available Artisan commands
php artisan make:model        # Create model (supports --factory, --migration, --seed, --controller, etc.)
php artisan make:controller   # Create controller
php artisan make:request      # Create form request
php artisan make:class        # Create generic PHP class
```

**Important**: Always pass `--no-interaction` flag when using Artisan commands programmatically.

## Architecture Overview

### Application Structure

#### Backend Organization
- **`app/Actions/`** - Single-purpose action classes (e.g., Fortify actions for user creation)
- **`app/Http/Controllers/`** - HTTP controllers (thin, delegate to services/actions)
- **`app/Http/Requests/`** - Form request validation classes
- **`app/Models/`** - Eloquent models
- **`app/Providers/`** - Service providers
- **`routes/web.php`** - Web routes (Inertia pages)
- **`routes/settings.php`** - Settings-related routes (included from web.php)
- **`config/fortify.php`** - Fortify authentication configuration

#### Frontend Organization
- **`resources/js/pages/`** - Inertia.js page components
  - `auth/` - Authentication pages (login, register, verify email)
  - `settings/` - User settings pages
  - `Welcome.vue` - Landing page
  - `Dashboard.vue` - Authenticated dashboard
- **`resources/js/components/`** - Reusable Vue components
  - Application-specific components (AppHeader, AppSidebar, etc.)
  - `ui/` - Reka UI components (shadcn-vue style): alert, avatar, badge, breadcrumb, button, card, checkbox, collapsible, dialog, dropdown-menu, input, label, navigation-menu, pin-input, separator, sheet, sidebar, skeleton, spinner, tooltip
- **`resources/js/layouts/`** - Layout components for pages
- **`resources/js/composables/`** - Vue composables for shared logic
- **`resources/js/routes/`** - TypeScript route definitions (wayfinder output)
- **`resources/js/actions/`** - Generated TypeScript route action helpers
- **`resources/js/types/`** - TypeScript type definitions

### Authentication System
This application uses **Laravel Fortify** with Inertia.js for authentication:

- **Enabled Features**:
  - User registration
  - Password reset
  - Email verification
  - Two-factor authentication (2FA) with confirmation
- **Home route**: `/dashboard` (see `config/fortify.php`)
- **Username field**: `email`
- **Guard**: `web`

### Component Library
The application includes **Reka UI** components (similar to shadcn-vue). Before creating new components, check `resources/js/components/ui/` for existing ones.

### Inertia.js Patterns

#### Rendering Pages
```php
// In controllers or routes
return Inertia::render('PageName', [
    'prop1' => $value1,
    'prop2' => $value2,
]);
```

#### Forms
Two approaches available:
1. **Form Component** (recommended for new code):
```vue
<Form :action="route" method="post" :data="formData">
  <!-- form fields -->
</Form>
```

2. **useForm Helper** (for programmatic control):
```typescript
const form = useForm({
  name: '',
  email: '',
});

form.post(route('endpoint'));
```

#### Deferred Props
Use deferred props for expensive data with skeleton loaders:
```php
return Inertia::render('Page', [
    'data' => Inertia::defer(fn() => expensiveQuery()),
]);
```

### Database Patterns
- **Always use Eloquent relationships** with explicit return types
- **Avoid N+1 queries**: Use eager loading (`with()`)
- **Prefer `Model::query()`** over `DB::` facade
- When creating models, also create factories and seeders

### Validation
- **Always create Form Request classes** for validation (not inline in controllers)
- Check existing Form Requests for array vs. string rule format consistency
- Include both validation rules and custom error messages

### Code Style

#### PHP
- Use **PHP 8 constructor property promotion**
- **Always use explicit return types** for methods
- Use **curly braces for all control structures** (even one-liners)
- Prefer **PHPDoc blocks** over inline comments
- **Use Enums** with TitleCase keys (e.g., `FavoritePerson`, `Monthly`)

#### TypeScript/Vue
- Follow existing conventions in sibling files
- Use **descriptive names** (e.g., `isRegisteredForDiscounts`, not `discount()`)
- Check for **existing components** before creating new ones

### Testing Requirements
- **Every change must be tested** (new test or update existing)
- Use **Pest v4** for testing
- Run minimum required tests for speed:
  ```bash
  php artisan test --filter TestName
  php artisan test tests/Feature/SpecificTest.php
  ```

## Mono-Repo Package Structure

This project uses a **mono-repo architecture** for the DeadDrop package ecosystem.

### Package Information
- **Package Name**: `3neti/dead-drop`
- **Root Namespace**: `LBHurtado\DeadDrop`
- **Location**: `packages/` directory

### Mono-Repo Organization

```
packages/
├── core-skeleton/          # Base models, contracts, traits
├── credential-vault/       # Hierarchical credential management
├── pipeline-engine/        # Pipeline orchestration system
├── processor-framework/    # Processor base classes and interfaces
├── ai-router/              # Multi-AI provider routing
├── queue-abstraction/      # Queue adapter layer
├── meta-campaign/          # Self-evolution AI system
└── stashlet/               # Embeddable Vue widgets
```

### Package Development Workflow

#### Creating a New Package
```bash
# Create package directory
mkdir -p packages/my-package/src
cd packages/my-package

# Initialize composer.json
composer init --name="lbhurtado/dead-drop-my-package" \
  --description="Description" \
  --type="library" \
  --license="MIT"

# Add to root composer.json repositories (already configured)
# Update dependencies
cd ../..
composer update
```

#### Package Structure Convention
Each package should follow this structure:
```
packages/my-package/
├── src/
│   ├── Contracts/          # Interfaces
│   ├── Services/           # Business logic
│   ├── Models/             # Eloquent models (if needed)
│   ├── Http/               # Controllers, requests (if needed)
│   └── MyPackageServiceProvider.php
├── tests/
│   ├── Unit/
│   └── Feature/
├── config/
│   └── my-package.php
├── database/
│   └── migrations/
├── composer.json
└── README.md
```

#### Testing Packages
```bash
# Run tests for specific package
php artisan test packages/my-package/tests

# Or using Pest directly
./vendor/bin/pest packages/my-package/tests
```

#### Package Naming Conventions
- **Composer name**: `lbhurtado/dead-drop-{package-name}`
- **Namespace**: `LBHurtado\DeadDrop\{PackageName}`
- **Directory**: `packages/{package-name}`

### Cross-Package Dependencies
Packages can depend on each other via Composer:
```json
{
  "require": {
    "lbhurtado/dead-drop-core-skeleton": "^1.0"
  }
}
```

### Autoloading
The root `composer.json` is configured to autoload all packages:
```json
{
  "autoload": {
    "psr-4": {
      "LBHurtado\\DeadDrop\\": "packages/*/src"
    }
  }
}
```

See `packages/README.md` for complete mono-repo documentation.

---

## AI Development with Laravel Boost

This project uses **Laravel Boost** to provide AI agents with deep contextual understanding of the Stash/DeadDrop platform.

### Installation
Boost is already installed and configured. MCP servers are available in:
- **Claude Code** (Desktop and VS Code)
- **PhpStorm** (Junie AI)

### Available MCP Tools
Laravel Boost provides 15+ powerful tools:

- **`search-docs`** - Search Laravel ecosystem documentation (version-aware for Laravel 12, Inertia v2, Pest v4, etc.)
- **`tinker`** - Execute PHP code in application context
- **`database-query`** - Query database directly (read-only)
- **`list-artisan-commands`** - List available Artisan commands with parameters
- **`get-absolute-url`** - Get correct project URL (scheme, domain, port)
- **`browser-logs`** - Read frontend browser logs and errors
- **`get-routes`** - List application routes
- **`get-models`** - List Eloquent models
- **`get-jobs`** - List queued jobs
- **`get-events`** - List application events
- **`get-config`** - Read configuration values

Full list: Run `php artisan boost:mcp --help`

### Custom Stash/DeadDrop Guidelines

Boost has been configured with **comprehensive custom guidelines** specific to this project in `.ai/guidelines/stash/`:

#### Domain Guidelines (`domain.md`)
Covers the complete Stash/DeadDrop architecture:
- Multi-tenancy patterns (Subscriber-based)
- Campaign system (workflows, processors, pipelines)
- Processor framework (OCR, Classification, Extraction, Validation)
- Credential vault (hierarchical: System → Subscriber → Campaign → Processor)
- Multi-AI routing (OpenAI, Anthropic, Gemini, Ollama, vLLM)
- Queue abstraction (Redis, SQS, RabbitMQ, Kafka)
- Document lifecycle and storage
- Stashlets (embeddable Vue widgets)
- Air-gapped deployment support

#### Testing Guidelines (`testing.md`)
Comprehensive testing patterns:
- Pest v4 usage (datasets, hooks, expectations)
- Factory patterns for test data
- Mocking strategies (AI providers, queues, storage, events)
- Testing processors and pipelines
- Multi-tenant testing patterns
- Coverage requirements (80% minimum)
- CI/CD strategy

#### Meta-Campaign Guidelines (`meta-campaign.md`)
Self-evolution system documentation:
- Intent classification and planning
- Code location via RAG + embeddings
- Patch generation and validation
- CI orchestration and PR creation
- Safety guardrails (restricted paths, policy engine, sandbox execution)
- Monitoring and audit trails
- Error handling and rollback procedures

### Using MCP Tools

**Always use `search-docs` first** before implementing Laravel/Inertia/Pest/Tailwind features:

```typescript
// Good - multiple related queries
search-docs(['rate limiting', 'route middleware', 'throttle'])

// Good - specific feature
search-docs(['inertia deferred props'])

// Bad - too vague
search-docs(['framework'])
```

**Important Guidelines**:
- Do NOT include package names in queries (version info is auto-included)
- Pass multiple related queries for comprehensive results
- Read recent browser logs only (old logs not useful)
- Use `list-artisan-commands` to verify parameters before running commands
- Use `tinker` for quick database queries or testing code snippets

### Updating Boost

After updating dependencies, refresh Laravel ecosystem guidelines:
```bash
php artisan boost:update
```

This updates version-aware documentation for:
- Laravel (currently v12)
- Inertia.js (v2)
- Pest (v4)
- Tailwind CSS (v4)
- Vue (v3)
- And other ecosystem packages

## TDD Workflow for Multi-Tenant Database Debugging

When you encounter database connection errors in live browser features (e.g., "SQLSTATE[42P01]: Undefined table"), follow the proven 4-Phase TDD Workflow documented in `.ai/guidelines/stash/tdd-tenancy-workflow.md`.

Key Principle: Always start with failing tests. Never modify production code without first reproducing the bug in a test.

### Quick Reference

- Phase 1: Write failing Feature tests in `tests/Feature/DeadDrop/` with `DeadDropTestCase`
- Phase 2: Debug using checklist to identify root cause category
- Phase 3: Implement fix (minimal change or auto-provision approach) + verify no regressions
- Phase 4: Enable Dusk browser test to verify end-to-end fix

### Best Practices for Multi-Tenant Testing

Do this:
- Create tests in `tests/Feature/DeadDrop/` directory (not generic Feature directory)
- Extend `DeadDropTestCase` as base class
- Use `TenantContext::run($tenant, function () { /* test code */ })`
- Run full test suite after each phase: `php artisan test`
- Write 3+ tests covering different scenarios

Avoid this:
- Modifying production code without a failing test first
- Debugging randomly without Phase 2 investigation
- Committing fixes that break existing tests
- Assuming PostgreSQL allows CREATE DATABASE inside transactions
- Using generic `TestCase` for tenant-related tests

### Common Multi-Tenant Issues & Quick Fixes

| Error | Root Cause | Fix |
|-------|-----------|-----|
| "Undefined table" | Model using 'tenant' connection when not initialized | Ensure `TenantContext::run()` wraps test code |
| "database ... does not exist" | Individual tenant DB missing in tests | Auto-create databases in `TenantConnectionManager` |
| "cannot run inside transaction" | PostgreSQL DDL in active transaction | Commit transaction before `CREATE DATABASE` |
| Connection not switching | Middleware not running or tenant not initialized | Check `InitializeTenantFromUser` middleware order |

For detailed troubleshooting, see `.ai/guidelines/stash/tdd-tenancy-workflow.md`.

## Project Conventions

### File Structure
- **Stick to existing directory structure** - don't create new base folders without approval
- Use `php artisan make:*` commands to create new files when possible
- Check sibling files for correct structure, approach, and naming

### Frontend Development
If changes aren't reflected in the UI, the user may need to:
- Run `npm run build`
- Run `npm run dev` 
- Run `composer run dev`

### Documentation
- **Only create documentation files** when explicitly requested
- Be concise - focus on important details, not obvious ones

### URL Generation
- Prefer **named routes** and the `route()` helper
- Use `get-absolute-url` tool when sharing URLs with users

### Version Control
Before running git commands that might use a pager, use `--no-pager`:
```bash
git --no-pager diff
git --no-pager log
```

## Important Notes

### Do NOT
- Change application dependencies without approval
- Create verification scripts when tests already cover functionality
- Make up information not present in the codebase
- Allow empty `__construct()` methods with zero parameters
- Use comments within code (use PHPDoc blocks instead)
- Commit changes unless explicitly requested by the user

### Do
- Follow existing code conventions in the application
- Use Laravel Boost's `search-docs` before implementing features
- Run tests after changes to ensure nothing breaks
- Use Wayfinder-generated routes in Vue components (type-safe)
- Eager load relationships to prevent N+1 queries
- Create Form Requests for validation (not inline validation)

## Common Issues

### Frontend Changes Not Appearing
The user needs to restart the dev server:
```bash
composer run dev  # or npm run dev
```

### TypeScript Route Errors
Regenerate Wayfinder routes:
```bash
php artisan wayfinder:generate
```

### Test Failures After Migration
Run fresh migrations in test environment:
```bash
php artisan test --env=testing
```

## Laravel Workflow for Document Processing

The application uses **Laravel Workflow** (inspired by Temporal) for durable document processing pipelines. Workflows provide automatic checkpointing, retry logic, and parallel execution capabilities.

### Architecture

```
DocumentProcessingPipeline
  ↓
DocumentProcessingWorkflow (durable, resumable)
  ├── OcrActivity
  ├── ClassificationActivity
  ├── ExtractionActivity
  └── ValidationActivity
```

### Feature Flag

Workflows are enabled by default. To disable (e.g., for legacy testing):

```bash
# .env
USE_LARAVEL_WORKFLOW=false # Disable workflows (not recommended)
```

### Starting a Workflow

```php
use App\Workflows\DocumentProcessingWorkflow;
use Workflow\WorkflowStub;

// Start workflow
$workflow = WorkflowStub::make(DocumentProcessingWorkflow::class);
$workflow->start($documentJobId, $tenantId);

// Workflows execute asynchronously - use events to track completion
```

### Workflow Structure

Workflows use PHP generators with `yield` for checkpointing:

```php
class DocumentProcessingWorkflow extends Workflow
{
    public function execute(string $documentJobId, string $tenantId)
    {
        // Each yield creates a checkpoint
        $ocrResult = yield ActivityStub::make(
            OcrActivity::class,
            $documentJobId,
            $tenantId
        );

        // If workflow crashes here, it resumes from this point
        $classificationResult = yield ActivityStub::make(
            ClassificationActivity::class,
            $documentJobId,
            $ocrResult,
            $tenantId
        );

        return compact('ocrResult', 'classificationResult');
    }
}
```

### Advanced Features

#### 1. Conditional Execution

Use native PHP conditionals for document-type routing:

```php
// Route based on OCR result
$documentType = $ocrResult['document_type'] ?? 'generic';

if ($documentType === 'invoice') {
    $result = yield ActivityStub::make(InvoiceExtractionActivity::class, ...);
} elseif ($documentType === 'receipt') {
    $result = yield ActivityStub::make(ReceiptExtractionActivity::class, ...);
} else {
    $result = yield ActivityStub::make(GenericExtractionActivity::class, ...);
}

// Or use match expression (PHP 8+)
$activityClass = match ($documentType) {
    'invoice' => InvoiceExtractionActivity::class,
    'receipt' => ReceiptExtractionActivity::class,
    default => GenericExtractionActivity::class,
};

$result = yield ActivityStub::make($activityClass, ...);
```

#### 2. Parallel Execution

Run activities simultaneously using `ActivityStub::all()`:

```php
// Run classification and extraction in parallel
[$classificationResult, $extractionResult] = yield ActivityStub::all([
    ActivityStub::make(ClassificationActivity::class, $jobId, $ocrResult, $tenantId),
    ActivityStub::make(ExtractionActivity::class, $jobId, $ocrResult, $tenantId),
]);

// Both complete before continuing
$validationResult = yield ActivityStub::make(
    ValidationActivity::class,
    $jobId,
    compact('classificationResult', 'extractionResult'),
    $tenantId
);
```

#### 3. Retry Configuration

Configure per-activity retries and timeouts:

```php
class OcrActivity extends Activity
{
    public $tries = 5;      // Max retry attempts
    public $timeout = 300;  // Timeout in seconds (5 minutes)

    public function execute(...): array
    {
        // Activity logic here
    }
}
```

#### 4. Error Handling

Use `NonRetryableException` for permanent failures:

```php
use Workflow\Exceptions\NonRetryableException;

class OcrActivity extends Activity
{
    public function execute(string $jobId, string $tenantId): array
    {
        // ...

        if (!$processorConfig) {
            // Don't retry configuration errors
            throw new NonRetryableException('OCR processor not found in config');
        }

        if ($temporaryError) {
            // Retry automatically (up to $tries limit)
            throw new \RuntimeException('Temporary OCR service error');
        }

        // Check for permanent vs temporary failures
        if (str_contains($error, 'unsupported file')) {
            throw new NonRetryableException($error);
        }

        throw new \RuntimeException($error); // Will retry
    }
}
```

### Testing Workflows

Use `WorkflowStub::fake()` for synchronous testing:

```php
use Workflow\WorkflowStub;

public function test_workflow_execution(): void
{
    WorkflowStub::fake();

    // Mock activities
    WorkflowStub::mock(OcrActivity::class, ['text' => 'Sample text']);
    WorkflowStub::mock(ClassificationActivity::class, ['category' => 'invoice']);

    // Execute workflow (synchronous in test mode)
    $workflow = WorkflowStub::make(DocumentProcessingWorkflow::class);
    $workflow->start($jobId, $tenantId);

    // Assert
    $result = $workflow->output();
    $this->assertArrayHasKey('ocr', $result);
    WorkflowStub::assertDispatched(OcrActivity::class);
}
```

### Creating New Activities

Activities wrap existing `ProcessorInterface` implementations:

```php
use Workflow\Activity;

class MyActivity extends Activity
{
    public $tries = 3;
    public $timeout = 120;

    public function execute(string $documentJobId, string $tenantId): array
    {
        // 1. Initialize tenant context
        $tenant = Tenant::on('central')->findOrFail($tenantId);
        app(TenancyService::class)->initializeTenant($tenant);

        // 2. Load DocumentJob
        $job = DocumentJob::findOrFail($documentJobId);

        // 3. Get processor from registry
        $processor = app(ProcessorRegistry::class)->get('my-processor');

        // 4. Execute processor (NO CHANGES to existing processors)
        $result = $processor->handle($job->document, $config, $context);

        // 5. Return results
        return $result->output;
    }
}
```

### Workflow Events

Listen for workflow lifecycle events:

```php
// app/Providers/AppServiceProvider.php
use Workflow\Events\{WorkflowCompleted, WorkflowFailed};

Event::listen(WorkflowCompleted::class, WorkflowCompletedListener::class);
Event::listen(WorkflowFailed::class, WorkflowFailedListener::class);
```

Event structure:
```php
class WorkflowCompleted
{
    public function __construct(
        public int|string $workflowId,
        public string $output,        // JSON result or error message
        public string $timestamp,     // ISO 8601 format
    ) {}
}
```

### Queue Requirements

**IMPORTANT**: Laravel Workflow requires queue workers:

```bash
# Development (already included in composer run dev)
php artisan queue:work

# Cannot use sync driver
QUEUE_CONNECTION=redis  # or database, sqs, etc.
```

### Multi-Tenant Configuration

Workflows use **central database** for state storage:

```php
// config/workflows.php
return [
    'stored_workflow_model' => App\Models\Workflow\StoredWorkflow::class,
];

// app/Models/Workflow/StoredWorkflow.php
class StoredWorkflow extends BaseStoredWorkflow
{
    protected $connection = 'central'; // Force central DB
}
```

### Migration Status

- ✅ Phase 1-2: All activities created, tests passing
- ✅ Phase 3: Feature flag integration complete
- ✅ Phase 4: Advanced features demonstrated
- ✅ Phase 5: Legacy code removed, workflows enabled by default

### Key Benefits

**Code Reduction**: 56% reduction (from ~873 lines to ~380 lines)

| Feature | Laravel Workflow |
|---------|------------------|
| State Management | Automatic via yield |
| Retry Logic | Activity-level (granular) |
| Parallel Execution | Native via `ActivityStub::all()` |
| Conditional Routing | Native PHP if/match |
| Workflow History | Automatic event sourcing |
| Resume After Crash | Resumes from last checkpoint |

### Monitoring Tools

The application includes two monitoring dashboards for observing workflow execution and queue processing in real-time.

#### Waterline (Workflow Monitoring)

Waterline provides visual monitoring for Laravel Workflow executions:

**Features**:
- Workflow execution history and status
- Activity-level progress tracking
- Checkpoint visualization
- Retry attempts and failures
- Execution timeline and duration
- Workflow output inspection

**Access**:
```
http://stash.test:8000/waterline
```

**Requirements**:
- Must be authenticated (protected by `auth` middleware)
- Workflows stored in central database

**Configuration**: `config/waterline.php`

#### Horizon (Queue Monitoring)

Horizon provides monitoring and management for Redis queues:

**Features**:
- Real-time queue metrics (throughput, runtime, failures)
- Job monitoring and retry management
- Worker supervision and load balancing
- Failed job tracking and manual retry
- Queue prioritization
- Metrics dashboard (jobs per minute, wait times)

**Access**:
```
http://stash.test:8000/horizon
```

**Requirements**:
- Redis connection (`QUEUE_CONNECTION=redis`)
- Authenticated users in local environment
- Gate authorization for production (see `HorizonServiceProvider`)

**Starting Horizon**:
```bash
php artisan horizon        # Production
composer run dev           # Development (includes horizon)
```

**Configuration**: `config/horizon.php`

#### Usage Tips

**Debugging Workflow Issues**:
1. Check Waterline for workflow status and checkpoint progress
2. Check Horizon for queue job failures
3. Review activity-specific errors in workflow logs
4. Use `php artisan pail` for real-time log streaming

**Performance Monitoring**:
- Monitor queue wait times in Horizon
- Track workflow execution duration in Waterline
- Identify bottleneck activities via timeline visualization
- Optimize retry strategies based on failure patterns

### Reference

- Main workflow: `app/Workflows/DocumentProcessingWorkflow.php`
- Advanced examples: `app/Workflows/AdvancedDocumentProcessingWorkflow.php`
- Activities: `app/Workflows/Activities/`
- Tests: `tests/Feature/Workflows/`
- Documentation: `LARAVEL_WORKFLOW_ARCHITECTURE.md`
