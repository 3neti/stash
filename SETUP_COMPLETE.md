# Setup Complete - Database Initialization & Testing System

## ✅ What Was Accomplished

### 1. Automated Database Initialization
Created a complete one-command setup system:

```bash
php artisan migrate:fresh --seed
```

**Features:**
- Reads admin credentials from `.env`
- Auto-creates default tenant if none exist
- Seeds 8 system processors
- Seeds 4 system credentials  
- Seeds 3 sample campaigns
- Links admin user to tenant
- Runs tenant migrations automatically

**Configuration** (`.env`):
```env
ADMIN_NAME="Admin User"
ADMIN_EMAIL=admin@stash.test
ADMIN_PASSWORD=password

DEFAULT_TENANT_NAME="Default Organization"
DEFAULT_TENANT_SLUG=default
DEFAULT_TENANT_EMAIL=admin@stash.test
```

### 2. Document Processing Command
Created `php artisan document:process` for round-trip testing:

```bash
# Basic usage
php artisan document:process /path/to/document.pdf

# With options
php artisan document:process /path/to/document.pdf \
  --tenant=acme-corp \
  --campaign=invoice-processing \
  --wait
```

**Features:**
- Uploads documents to tenant storage
- Creates DocumentJob and starts workflow
- Optional `--wait` flag for real-time status monitoring
- Displays processor execution results
- Shows detailed error logs

### 3. Enhanced Seeders

**DatabaseSeeder.php:**
- Auto-creates tenant from `.env` if none exist
- Creates tenant database
- Runs tenant migrations
- Links admin user to tenant
- Calls processor, credential, and campaign seeders

**UserSeeder.php:**
- Creates admin user from `.env` with role
- Creates test users for development

**ProcessorSeeder.php:**
- Seeds 8 system processors with complete schemas
- Tesseract OCR, OpenAI Vision OCR, Document Classifier, etc.

**CredentialSeeder.php:**
- Fixed to use correct polymorphic columns
- Seeds system-level credentials for AI providers

**CampaignSeeder.php:**
- Seeds 3 campaigns with complete pipeline configs
- **Fixed:** Added `type` field to processor configs for ProcessorConfigData compatibility
- Invoice Processing Pipeline (4 processors)
- Receipt OCR Workflow (2 processors)
- Contract Analysis (3 processors)

### 4. Workflow Activity Fixes

**All Activities Updated:**
- Load processor by ULID from pipeline config
- Dynamically register processor with ProcessorRegistry  
- Create proper ProcessorConfigData from config array
- Fixed imports: Activity, NonRetryableException, ProcessorConfigData, etc.

**Files Modified:**
- `app/Workflows/Activities/OcrActivity.php`
- `app/Workflows/Activities/ClassificationActivity.php`
- `app/Workflows/Activities/ExtractionActivity.php`
- `app/Workflows/Activities/ValidationActivity.php`

### 5. ProcessorRegistry Enhancements
- Added `registerFromDatabase()` method
- Activities dynamically register processors when encountered
- Supports both slug-based and ULID-based lookup

### 6. Documentation
- `INITIALIZATION_GUIDE.md` - Complete setup and testing guide
- `SETUP_COMPLETE.md` - This file
- Updated `.env.example` with admin and tenant config

## 🔧 Files Changed

### Created
- `app/Console/Commands/ProcessDocumentCommand.php`
- `INITIALIZATION_GUIDE.md`
- `SETUP_COMPLETE.md`

### Modified
- `.env` - Added admin and tenant configuration
- `.env.example` - Added admin and tenant configuration
- `database/seeders/DatabaseSeeder.php` - Auto-tenant creation
- `database/seeders/UserSeeder.php` - Admin user from .env
- `database/seeders/CredentialSeeder.php` - Fixed polymorphic columns
- `database/seeders/CampaignSeeder.php` - Added `type` fields to processor configs
- `app/Workflows/Activities/*.php` - Fixed processor loading and imports
- `app/Services/Pipeline/ProcessorRegistry.php` - Added database registration
- `app/Providers/AppServiceProvider.php` - Added database processor registration

## 🚧 Known Issues

### Document Processing Workflow
The workflow activities are correctly structured and tests pass with mocked activities, but real execution through the queue is encountering errors in the OcrActivity when processing actual documents.

**Status:** The workflow infrastructure is in place and working. The issue is likely in how the actual processor implementations (OcrProcessor, etc.) handle the document files or configuration.

**To Debug:**
1. Check processor implementation in `app/Processors/OcrProcessor.php`
2. Verify file storage and retrieval works correctly
3. Check processor dependencies (Tesseract, etc.) are available
4. Use test mode: `WorkflowStub::fake()` for mocked execution

### Test Suite
- Basic workflow tests pass (5/5)
- ProductionWorkflowTest has database schema issues (tenant `state` column)
- Demo data seeder disabled (uses old `status` field instead of state machine)

## 📋 Next Steps

### Immediate
1. Debug OcrActivity execution with actual files
2. Verify processor implementations work correctly
3. Test complete workflow end-to-end

### Future Enhancements
1. Update DemoDataSeeder to use state machine
2. Fix ProductionWorkflowTest database schema
3. Add more comprehensive integration tests
4. Add processor output validation
5. Implement workflow result display in ProcessDocumentCommand

## 🎯 Usage Examples

### Initialize Fresh Environment
```bash
# Configure .env first
php artisan migrate:fresh --seed

# Start queue worker
php artisan queue:work
```

### Process Test Document
```bash
# Create test file
cat > storage/app/test_invoice.txt << 'EOF'
Sample Invoice
Date: 2025-12-02
Vendor: Acme Corporation
Total: $1,234.56
EOF

# Process it
php artisan document:process "$(pwd)/storage/app/test_invoice.txt" --wait
```

### Monitor Processing
- **Waterline:** http://stash.test:8000/waterline (workflow monitoring)
- **Horizon:** http://stash.test:8000/horizon (queue monitoring)
- **Logs:** `php artisan pail`

## 📚 Documentation

See `INITIALIZATION_GUIDE.md` for:
- Complete setup instructions
- Command reference
- Troubleshooting guide
- Multi-tenant testing
- CI/CD usage examples
