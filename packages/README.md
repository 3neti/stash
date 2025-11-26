# DeadDrop Mono-Repo Packages

This directory contains the DeadDrop package ecosystem. Each package is a self-contained Laravel package that can be developed independently while being integrated into the main Stash application.

## Package Namespace

**Root Namespace:** `LBHurtado\DeadDrop`  
**Package Name:** `3neti/dead-drop`

## Package Structure

```
packages/
├── core-skeleton/          # Domain models (Subscriber, Campaign, Credential, ProcessRun)
├── core-storage/           # Storage abstraction (S3, local)
├── core-workflow/          # Pipeline engine and processors
├── core-events/            # Event bus and activity logs
├── core-auth/              # Multi-tenant authentication
├── core-ui/                # Inertia.js + Vue components
├── core-actions/           # Application actions
├── core-guardrails/        # Policy engine and safe operations
├── meta-campaign/          # AI-driven evolution brain
├── boost/                  # Code generator
├── infra-api-gateway/      # API gateway and throttling
├── infra-secrets/          # Credential vault
└── infra-telemetry/        # Observability and monitoring
```

## Package Naming Convention

- **core-\*** - Core functionality packages
- **infra-\*** - Infrastructure packages
- **meta-\*** - Meta-Campaign related packages

## Creating a New Package

1. Create package directory:
   ```bash
   mkdir -p packages/package-name
   ```

2. Create `composer.json`:
   ```json
   {
       "name": "lbhurtado/deaddrop-package-name",
       "description": "Package description",
       "type": "library",
       "license": "MIT",
       "autoload": {
           "psr-4": {
               "LBHurtado\\DeadDrop\\PackageName\\": "src/"
           }
       },
       "require": {
           "php": "^8.2",
           "laravel/framework": "^12.0"
       }
   }
   ```

3. Create standard package structure:
   ```
   package-name/
   ├── composer.json
   ├── src/
   │   ├── PackageNameServiceProvider.php
   │   └── ...
   ├── config/
   │   └── package-name.php
   ├── database/
   │   └── migrations/
   ├── tests/
   │   └── TestCase.php
   └── README.md
   ```

4. Register service provider in `bootstrap/providers.php`

5. Run composer update:
   ```bash
   composer update
   ```

## Development Workflow

> **Note:** This repository uses a hybrid Herd + Sail setup. See [DEVELOPMENT.md](../DEVELOPMENT.md) for complete environment documentation.

### Creating a New Package

```bash
# Navigate to package
cd packages/core-skeleton

# Run tests for specific package
../../vendor/bin/pest

# Format code
../../vendor/bin/pint
```

### Installing Package Dependencies

```bash
# From root
composer require lbhurtado/deaddrop-core-skeleton:@dev

# Composer will symlink from packages/ directory
```

### Auto-Discovery

All packages use Laravel's package auto-discovery. Service providers are automatically registered.

## Testing

Each package should have its own test suite:

```
package-name/
└── tests/
    ├── TestCase.php
    ├── Unit/
    │   └── ExampleTest.php
    └── Feature/
        └── ExampleFeatureTest.php
```

Run all tests from root:
```bash
composer test
```

Run specific package tests:
```bash
./vendor/bin/pest packages/core-skeleton/tests
```

## Dependencies Between Packages

Packages can depend on each other by requiring them in their `composer.json`:

```json
{
    "require": {
        "lbhurtado/deaddrop-core-skeleton": "^1.0"
    }
}
```

### Dependency Graph

```
core-skeleton (base)
  ├── core-storage (depends on skeleton)
  ├── core-workflow (depends on skeleton)
  ├── core-auth (depends on skeleton)
  └── ...

meta-campaign (depends on core-*)
```

## Package Development Guidelines

1. **Single Responsibility** - Each package should have one clear purpose
2. **Minimal Dependencies** - Only require what you need
3. **Comprehensive Tests** - Aim for >95% coverage
4. **Documentation** - Every package needs a README
5. **Type Safety** - Use strict types and PHPStan
6. **Laravel Conventions** - Follow Laravel package development standards

## Publishing Packages

Individual packages can be published to Packagist:

1. Extract package to separate repository
2. Tag version (e.g., `v1.0.0`)
3. Submit to Packagist
4. Update main repo to use published version

## Meta-Campaign Integration

The Meta-Campaign can generate new packages automatically. See `packages/meta-campaign/README.md` for details.

## Links

- [Main Application](../)
- [Implementation Plan](../ROADMAP.md)
- [Package Development Guide](../WARP.md)
