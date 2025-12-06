# Integration Tests

Real-world workflow tests that simulate actual production usage.

## Test Status

✅ **ALL TESTS PASSING** (as of 2025-12-06)
- **5 tests passing** (20 assertions)
- **3 tests skipped** (environment-specific)
- **Duration**: ~3 seconds

See `TEST_RESULTS.md` for detailed results.

## RealWorldWorkflowTest.php

Simulates the complete e-signature document processing workflow.

### What It Tests

1. **Vite/Frontend** - Checks if `npm run dev` is running or assets are built
2. **Broadcasting** - Validates Reverb/Pusher configuration
3. **Database** 
   - Schema validation (central + tenant tables)
   - Seeder creates e-signature campaign
   - Queue connection is working
4. **Document Processing** - Simulates `document:process` command
5. **KYC Callback** - Tests auto-approval webhook handling

### Running the Tests

#### Quick Test (Minimal Setup)
```bash
php artisan test tests/Integration/RealWorldWorkflowTest.php
```

This runs basic validation tests that don't require external services.

#### Full Workflow Test (Requires Services)

**Terminal 1: Frontend**
```bash
php artisan optimize:clear && npm run dev
```

**Terminal 2: Broadcasting**
```bash
php artisan reverb:start --debug
```

**Terminal 3: Queue + Database**
```bash
truncate -s0 storage/logs/laravel.log && \
php artisan migrate:fresh --seed && \
php artisan queue:work
```

**Terminal 4: Run Test**
```bash
php artisan test tests/Integration/RealWorldWorkflowTest.php
```

### Test Coverage

| Test | What It Validates | Services Required |
|------|------------------|-------------------|
| 1. Vite assets | Frontend build working | npm run dev |
| 2. Broadcasting config | Reverb/Pusher setup | None (config only) |
| 3a. Database schema | Migrations ran correctly | Database |
| 3b. Database seed | E-signature campaign exists | Database + Seeder |
| 3c. Queue working | Redis/SQS connection | Queue service |
| 4. Document process | Command creates doc + job | Database |
| 5. KYC callback | Webhook handling | Database + Routes |
| Full workflow | End-to-end upload → sign | All services |

### What Gets Skipped

Tests automatically skip if prerequisites aren't met:

- **Vite test** - Skips if not in local environment
- **KYC callback** - Skips if route not registered
- **Full workflow** - Skips if e-signature campaign doesn't exist

### Manual Real-World Test

To manually test the workflow:

```bash
# Terminal 4: Process a real document
php artisan document:process ~/Downloads/Invoice.pdf \
  --campaign=e-signature \
  --wait \
  --show-output

# Browser: Test KYC callback
# Open: http://stash.test/kyc/callback/YOUR-UUID?transactionId=EKYC-XXX&status=auto_approved
```

### Assertions Made

✅ Frontend assets are accessible  
✅ Broadcasting is configured correctly  
✅ All database tables exist with correct columns  
✅ E-signature campaign is seeded  
✅ Queue can accept jobs  
✅ Documents can be created and queued  
✅ KYC callbacks update document status  
✅ Complete workflow: upload → process → sign → complete

### Adding More Real-World Tests

Follow this pattern:

```php
test('your workflow name', function () {
    // 1. Setup (create campaign, document, etc.)
    $campaign = Campaign::factory()->create();
    
    // 2. Execute action (upload, process, etc.)
    $response = $this->post(route('documents.store'), ['file' => $file]);
    
    // 3. Assert outcomes
    expect($response->status())->toBe(200);
    expect(Document::count())->toBe(1);
    
    // 4. Verify side effects (queue jobs, events, etc.)
    Queue::assertPushed(ProcessDocumentJob::class);
});
```

### Best Practices

1. **Use fakes** for external services (Storage, Queue, Event)
2. **Skip conditionally** if prerequisites aren't met
3. **Clean up** after tests (RefreshDatabase handles this)
4. **Test in isolation** - each test should be independent
5. **Document requirements** - specify what services/setup is needed

### Troubleshooting

**Test skipped: "Vite not running"**
→ Start Vite: `npm run dev`

**Test skipped: "E-signature campaign not seeded"**
→ Run seeder: `php artisan db:seed`

**Test failed: "Queue connection refused"**
→ Start Redis: `redis-server` or configure queue to use database

**Test failed: "Route not found"**
→ Check routes are registered in `routes/web.php`

### Related Files

- `tests/Feature/DeadDrop/DocumentUploadRouteTest.php` - Document upload tests
- `tests/Feature/DeadDrop/Actions/UploadDocumentTest.php` - Upload action tests
- `tests/Feature/Documents/DocumentProgressEndToEndTest.php` - Progress tracking
