# Development Environment Guide

## Hybrid Development Setup: Laravel Herd + Laravel Sail

This project uses a **hybrid approach** combining Laravel Herd for daily development and Laravel Sail/Docker for isolated testing and Meta-Campaign execution.

---

## Primary Development: Laravel Herd

**URL:** `http://stash.test`

### Why Herd?
- ✅ Fast and lightweight
- ✅ Native macOS integration
- ✅ Instant PHP switching
- ✅ No Docker overhead for daily coding

### What Herd is Used For:
- Day-to-day coding and debugging
- Quick feature development
- Frontend work (Vue/Inertia)
- Database migrations and seeders
- Artisan commands
- Package development

### Herd Services:
- **PHP:** 8.4 (managed by Herd)
- **Database:** PostgreSQL (via Herd or separate install)
- **Redis:** Local Redis server
- **Web Server:** Herd's built-in server

---

## Testing & Meta-Campaign: Laravel Sail/Docker

**URL:** `http://localhost`

### Why Sail?
- ✅ Isolated sandbox environment
- ✅ Production-like services (PostgreSQL, Redis, Minio)
- ✅ Safe Meta-Campaign code execution
- ✅ Consistent CI/CD environment
- ✅ Multiple service orchestration

### What Sail is Used For:
- **Meta-Campaign Execution** - AI-generated code runs in sandboxed containers
- **Integration Testing** - Test with full service stack
- **CI/CD Pipelines** - GitHub Actions uses same environment
- **Package Testing** - Test mono-repo packages in isolation
- **S3/Minio Testing** - Test document storage workflows

### Sail Services:
- **PHP:** 8.4 (Docker container)
- **PostgreSQL:** 18-alpine
- **Redis:** Alpine
- **Minio:** S3-compatible storage
- **App:** Port 80
- **Minio Console:** Port 8900

---

## Quick Start

### Daily Development (Herd)
```bash
# Your site is automatically available at stash.test
# Just start coding!

php artisan serve      # If you need to test on different port
php artisan migrate    # Run migrations
php artisan test       # Run tests locally
npm run dev            # Start Vite
```

### Sail Testing
```bash
# Start Sail environment
./sail-env.sh up

# Run tests in Sail
./sail-env.sh test

# Run Meta-Campaign in sandbox
./sail-env.sh meta

# Stop Sail (back to Herd)
./sail-env.sh down
```

---

## Environment Files

### `.env` (Herd Development)
- Primary development configuration
- Uses `http://stash.test`
- Database: `127.0.0.1:5432` (local PostgreSQL)
- Redis: `127.0.0.1:6379` (local Redis)
- Storage: `local` disk

### `.env.sail` (Docker Testing)
- Sail/Docker configuration
- Uses `http://localhost`
- Database: `pgsql:5432` (Docker service)
- Redis: `redis:6379` (Docker service)
- Storage: `s3` (Minio)
- Meta-Campaign sandbox enabled

---

## Development Workflows

### 1. Feature Development (Herd)
```bash
# Create a new feature branch
git checkout -b feature/awesome-feature

# Code in your favorite IDE
# Site auto-refreshes at stash.test

# Run tests locally
php artisan test

# Commit your changes
git add .
git commit -m "Add awesome feature"
```

### 2. Integration Testing (Sail)
```bash
# Start Sail
./sail-env.sh up

# Run full test suite with all services
./vendor/bin/sail artisan test

# Test S3 storage
./vendor/bin/sail artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'Hello Minio!');

# Stop Sail when done
./sail-env.sh down
```

### 3. Meta-Campaign Development (Sail)
```bash
# Start Sail sandbox
./sail-env.sh up

# Run Meta-Campaign
./vendor/bin/sail artisan meta:new

# What feature do you want to add?
> "Add bulk document processing"

# [Meta-Campaign generates code in isolated container]
# [Tests run in sandbox]
# [Review bundle created]

# Stop sandbox
./sail-env.sh down
```

### 4. Package Development
```bash
# Develop packages in Herd
cd packages/core-skeleton
# Edit files...

# Test package in isolation with Sail
./sail-env.sh up
./vendor/bin/sail artisan test packages/core-skeleton/tests
```

---

## Service Access

### Herd (Always Running)
| Service | URL/Host |
|---------|----------|
| App | http://stash.test |
| PostgreSQL | 127.0.0.1:5432 |
| Redis | 127.0.0.1:6379 |

### Sail (When Running)
| Service | URL/Host | Credentials |
|---------|----------|-------------|
| App | http://localhost | - |
| PostgreSQL | localhost:5432 | sail / password |
| Redis | localhost:6379 | - |
| Minio | http://localhost:9000 | sail / password |
| Minio Console | http://localhost:8900 | sail / password |

---

## Common Commands

### Herd Development
```bash
# Run Artisan commands
php artisan migrate
php artisan make:model Campaign
php artisan tinker

# Run tests
php artisan test
./vendor/bin/pest

# Code formatting
./vendor/bin/pint

# Start dev server
composer run dev
```

### Sail Testing
```bash
# Start/stop services
./vendor/bin/sail up -d
./vendor/bin/sail down

# Run Artisan commands
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test

# Access containers
./vendor/bin/sail shell
./vendor/bin/sail root-shell

# View logs
./vendor/bin/sail logs
```

### Helper Script
```bash
# Quick Sail commands
./sail-env.sh up      # Start Sail
./sail-env.sh down    # Stop Sail
./sail-env.sh test    # Run tests in Sail
./sail-env.sh meta    # Run Meta-Campaign
```

---

## Troubleshooting

### Port Conflicts
If Sail ports conflict with Herd or other services:

```bash
# Edit compose.yaml and change ports
# For example, change app port from 80 to 8080:
ports:
  - '8080:80'
```

### Database Connection Issues
```bash
# Herd: Ensure PostgreSQL is running
brew services list
brew services start postgresql@15

# Sail: Check database service
./vendor/bin/sail ps
./vendor/bin/sail artisan migrate
```

### Redis Connection Issues
```bash
# Herd: Start local Redis
brew services start redis

# Sail: Check Redis service
./vendor/bin/sail redis-cli ping
```

### Minio Bucket Setup
```bash
# Create the bucket if it doesn't exist
./vendor/bin/sail artisan tinker
>>> Storage::disk('s3')->put('test.txt', 'test');
```

---

## CI/CD Integration

GitHub Actions uses the same Sail configuration:

```yaml
# .github/workflows/tests.yml
services:
  pgsql:
    image: postgres:18-alpine
  redis:
    image: redis:alpine
  minio:
    image: minio/minio:latest
```

This ensures **dev-prod parity** - if it works in Sail, it works in CI!

---

## Best Practices

### 1. Development Flow
- ✅ **Code in Herd** (fast iteration)
- ✅ **Test in Sail** (production-like)
- ✅ **Meta-Campaign in Sail** (safe sandbox)

### 2. Database Workflow
- ✅ Run migrations in both environments
- ✅ Use seeders for test data
- ✅ Keep schemas in sync

### 3. Storage Workflow
- ✅ Develop with `local` disk (Herd)
- ✅ Test with `s3` disk (Sail/Minio)
- ✅ Production uses real S3

### 4. Queue Workflow
- ✅ Use `sync` driver for quick dev (Herd)
- ✅ Use `redis` driver for testing (Sail)
- ✅ Test queue workers in Sail

---

## Meta-Campaign Sandbox

The Meta-Campaign uses Sail for safe code execution:

### Why Sandboxed?
- 🛡️ **Security** - AI-generated code runs in isolated container
- 🔒 **Resource Limits** - CPU/memory constraints prevent runaway processes
- 🧪 **Clean State** - Each run starts fresh
- 📊 **Monitoring** - Easy to track resource usage

### How It Works:
1. Meta-Campaign generates code patches
2. Patches applied in Sail container
3. Tests run in sandbox
4. Results collected
5. Container destroyed
6. You review the changes

### Configuration:
```env
# .env.sail
META_CAMPAIGN_SANDBOX=true
META_CAMPAIGN_TIMEOUT=300
META_CAMPAIGN_MAX_MEMORY=512M
```

---

## Summary

| Use Case | Environment | Why |
|----------|-------------|-----|
| **Daily Coding** | Herd | Fast, lightweight |
| **Integration Tests** | Sail | Full service stack |
| **Meta-Campaign** | Sail | Safe sandbox execution |
| **CI/CD** | Sail | Consistent environment |
| **Frontend Dev** | Herd | Hot reload, fast iteration |
| **Package Testing** | Sail | Clean, isolated tests |

**This hybrid approach gives you the best of both worlds:** speed for development, safety for testing! 🚀
