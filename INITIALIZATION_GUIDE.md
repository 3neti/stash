# Stash Initialization & Testing Guide

This guide documents the automated initialization and document processing workflow for the Stash application.

## Quick Start

Initialize the entire application environment with a single command:

```bash
php artisan migrate:fresh --seed
```

This command will:
1. Drop and recreate all databases (central and tenant)
2. Run all migrations
3. Create admin user from `.env` configuration
4. Auto-create default tenant if none exists
5. Seed processors, credentials, and campaigns

## Configuration

### Environment Variables

Add these variables to your `.env` file:

```env
# Admin User Configuration
ADMIN_NAME="Admin User"
ADMIN_EMAIL=admin@stash.test
ADMIN_PASSWORD=password

# Default Tenant Configuration
DEFAULT_TENANT_NAME="Default Organization"
DEFAULT_TENANT_SLUG=default
DEFAULT_TENANT_EMAIL=admin@stash.test
```

### What Gets Seeded

#### Central Database
- **Admin User**: Created from `ADMIN_EMAIL`, `ADMIN_NAME`, `ADMIN_PASSWORD`
- **Test User**: `test@example.com` / `password`
- **5 Additional Users**: Generated via factory
- **Default Tenant**: Auto-created if no tenants exist

#### Tenant Database (per tenant)
- **8 System Processors**:
  - Tesseract OCR
  - OpenAI Vision OCR
  - Document Classifier
  - Data Extractor
  - Schema Validator
  - Data Enricher
  - Email Notifier
  - S3 Storage

- **4 System Credentials**:
  - OpenAI API Key (from `OPENAI_API_KEY` env)
  - Anthropic API Key
  - AWS Access Key
  - AWS Secret Key

- **3 Campaigns**:
  - Invoice Processing Pipeline (active)
  - Receipt OCR Workflow (active)
  - Contract Analysis (draft)

## Document Processing Command

Process documents through the pipeline for testing:

```bash
php artisan document:process {file} [options]
```

### Arguments
- `file` - Absolute path to the document file (PDF, PNG, JPG, JPEG, TIFF)

### Options
- `--tenant=SLUG` - Tenant slug (defaults to first active tenant)
- `--campaign=SLUG` - Campaign slug (defaults to first active campaign)
- `--wait` - Wait and show processing status in real-time

### Examples

**Basic usage** (uploads and queues for processing):
```bash
php artisan document:process /path/to/invoice.pdf
```

**With specific tenant and campaign**:
```bash
php artisan document:process /path/to/receipt.jpg \
  --tenant=acme-corp \
  --campaign=receipt-ocr
```

**Wait for processing to complete**:
```bash
php artisan document:process /path/to/document.pdf --wait
```

### Output

The command displays:
- Tenant and campaign information
- Document upload confirmation
- Document UUID and metadata
- Document job UUID and initial status
- Storage path

With `--wait` flag, it also shows:
- Real-time processing status updates
- Completion/failure notification
- Processor execution results
- Error logs (if failed)

### Example Output

```
Processing document: /path/to/invoice.pdf

✓ Using tenant: Default Organization (default)
✓ Using campaign: Invoice Processing Pipeline (invoice-processing)

Uploading document...
✓ Document uploaded: 304d6d2d-b48c-402a-8c79-4d82dd0fe8d7
  - Filename: invoice.pdf
  - Size: 1.2 MB
  - Hash: e0c27b1fd71d5ad08e9d316a2ee6a72d...

✓ Document job created: d4a7d350-3aaa-43b7-9017-92540bd39e4b
  - Status: pending

✅ Document processing initiated successfully!

+---------------+------------------------------------------------------------------+
| Property      | Value                                                            |
+---------------+------------------------------------------------------------------+
| Document UUID | 304d6d2d-b48c-402a-8c79-4d82dd0fe8d7                             |
| Campaign      | Invoice Processing Pipeline                                      |
| Filename      | invoice.pdf                                                      |
| Status        | pending                                                          |
| Storage Path  | tenants/{tenant_id}/documents/2025/12/{id}_invoice.pdf           |
+---------------+------------------------------------------------------------------+
```

## Testing Workflow

### 1. Initialize Environment

```bash
# Full reset and seed
php artisan migrate:fresh --seed

# Queue worker must be running for processing
php artisan queue:work
# OR
composer run dev
```

### 2. Create Test Document

```bash
# Create a sample text file
cat > storage/app/test_invoice.txt << 'EOF'
Sample Invoice
Date: 2025-12-02
Vendor: Acme Corporation
Total: $1,234.56

Items:
- Widget A: $500.00
- Widget B: $734.56

Thank you for your business!
EOF
```

### 3. Process Document

```bash
# Get absolute path
INVOICE_PATH="$(pwd)/storage/app/test_invoice.txt"

# Process document
php artisan document:process "$INVOICE_PATH"

# OR with real-time monitoring
php artisan document:process "$INVOICE_PATH" --wait
```

### 4. Monitor Processing

**Via Waterline Dashboard** (Workflow monitoring):
```
http://stash.test:8000/waterline
```

**Via Horizon Dashboard** (Queue monitoring):
```
http://stash.test:8000/horizon
```

**Via Logs**:
```bash
php artisan pail
```

## Multi-Tenant Testing

### Create Additional Tenants

```bash
php artisan tenant:create "Acme Corp" \
  --slug=acme-corp \
  --email=admin@acme.com
```

### Process Document for Specific Tenant

```bash
php artisan document:process /path/to/document.pdf \
  --tenant=acme-corp \
  --campaign=invoice-processing
```

## Troubleshooting

### Issue: "No tenants found"

**Solution**: Add tenant configuration to `.env`:
```env
DEFAULT_TENANT_NAME="Default Organization"
DEFAULT_TENANT_SLUG=default
DEFAULT_TENANT_EMAIL=admin@stash.test
```

Then run:
```bash
php artisan migrate:fresh --seed
```

### Issue: "No published campaigns found"

**Solution**: Check campaigns are seeded and published:
```bash
php artisan tinker
>>> Campaign::whereNotNull('published_at')->get()
```

### Issue: Document stuck in "pending" state

**Cause**: Queue worker not running

**Solution**: Start queue worker:
```bash
php artisan queue:work
# OR
composer run dev
```

### Issue: "File not found" error

**Cause**: Relative path used instead of absolute path

**Solution**: Use absolute path:
```bash
php artisan document:process "$(pwd)/storage/app/document.pdf"
```

## Development Notes

### Database Structure

- **Central Database**: `stash` (PostgreSQL)
  - Stores: tenants, users, domains
  
- **Tenant Databases**: `tenant_{ulid}` (PostgreSQL)
  - Stores: campaigns, documents, processors, credentials, jobs

### State Management

Documents and DocumentJobs use **Spatie Model States**:

**Document States**:
- `PendingDocumentState`
- `QueuedDocumentState`
- `ProcessingDocumentState`
- `CompletedDocumentState`
- `FailedDocumentState`

**DocumentJob States**:
- `PendingJobState`
- `RunningJobState`
- `CompletedJobState`
- `FailedJobState`

### Workflow System

Documents are processed using **Laravel Workflow**:
- Durable execution with automatic checkpointing
- Activity-based architecture (OCR → Classification → Extraction → Validation)
- Automatic retry with configurable strategies
- Resumable after crashes

See `LARAVEL_WORKFLOW_ARCHITECTURE.md` for details.

## CI/CD Usage

For automated deployments:

```bash
# Set environment variables
export ADMIN_EMAIL=admin@production.com
export ADMIN_PASSWORD=$(openssl rand -base64 32)
export DEFAULT_TENANT_NAME="Production Org"
export DEFAULT_TENANT_SLUG=production

# Initialize database
php artisan migrate:fresh --seed --force

# Start services
php artisan queue:work &
php artisan serve
```

## Related Documentation

- `WARP.md` - Complete project documentation
- `LARAVEL_WORKFLOW_ARCHITECTURE.md` - Workflow system details
- `DEPLOYMENT_READY.md` - Deployment guide
- `TEST_FIX_SUMMARY.md` - Test suite status
- `TDD_TENANCY_WORKFLOW.md` - Multi-tenant testing guide
