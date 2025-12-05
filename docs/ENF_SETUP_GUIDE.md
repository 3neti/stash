# ENF Electronic Signature - Setup & Testing Guide

## ✅ Campaign Setup Complete

The **ENF Electronic Signature** campaign has been successfully seeded and is ready for testing.

### Campaign Details

- **Name**: ENF Electronic Signature
- **Slug**: `e-signature`
- **State**: Active
- **Description**: Electronic Notarization Framework: KYC verification + document signing with blockchain timestamps
- **Processors**: 2 (eKYC Verification → Electronic Signature)
- **Retention**: 2,555 days (7 years for legal compliance)

## 📋 Pipeline Configuration

### Step 1: eKYC Verification Processor
**Slug**: `ekyc-verification`  
**Category**: `validation`  
**Dependencies**: None (runs first)

**Configuration**:
```json
{
  "country": "PH",
  "transaction_id_prefix": "ENF",
  "contact_fields": {
    "mobile": "required",
    "name": "required",
    "email": "optional"
  }
}
```

**Output**:
- `transaction_id`: Generated KYC transaction ID (e.g., `ENF-1733363280-4567`)
- `kyc_link`: Onboarding URL for user to complete KYC
- `contact_id`: Created Contact record
- `kyc_status`: `"pending"` (becomes `"approved"` after webhook)

---

### Step 2: Electronic Signature Processor
**Slug**: `electronic-signature`  
**Category**: `signing`  
**Dependencies**: `['ekyc-verification']` ✅ **Enforced by database**

**Configuration**:
```json
{
  "transaction_id": "{{ekyc-verification.transaction_id}}",
  "tile": 1,
  "metadata": {
    "notarization_type": "ENF",
    "document_category": "Legal"
  }
}
```

**Output**:
- `signed_document`: PDF with ID stamp + QR watermark
- `stamp`: Signature stamp image
- `verification_url`: QR-scannable verification link
- `signer_info`: Contact details + KYC status
- `signature_timestamp`: ISO 8601 timestamp

## 🚀 How to Test the Campaign

### Option 1: CLI Command (Recommended for Testing)

```bash
php artisan document:process ~/Downloads/Invoice.pdf --campaign=e-signature --wait --show-output
```

**What happens**:
1. ✅ Document uploaded
2. ✅ EKycVerificationProcessor runs → generates KYC link
3. ⏸️ **Workflow pauses** → waiting for KYC completion
4. 📱 User must visit KYC link and complete verification
5. 🔄 HyperVerge webhook received → Contact status = `"approved"`
6. ✅ ElectronicSignatureProcessor runs → document signed
7. 📄 Signed PDF returned with QR watermark

### Option 2: Web API Upload

```bash
# Upload document
curl -X POST http://stash.test:8000/api/documents/upload \
  -H "Authorization: Bearer {token}" \
  -F "file=@/path/to/document.pdf" \
  -F "campaign_slug=e-signature"

# Response includes:
# - document_uuid
# - job_id
# - kyc_link (for user to complete verification)
```

### Option 3: Interactive Testing (Full Flow)

```bash
# 1. Start dev server (includes queue workers + Horizon)
composer run dev

# 2. In another terminal, upload document
php artisan document:process ~/Downloads/contract.pdf --campaign=e-signature

# 3. Watch logs in real-time
# The dev server shows queue processing, workflow events, and KYC webhooks

# 4. Complete KYC via the generated link (shown in logs)

# 5. Watch ElectronicSignatureProcessor execute automatically
```

## 🔍 Monitoring the Workflow

### 1. Waterline (Workflow Dashboard)
```
http://stash.test:8000/waterline
```
- View workflow execution in real-time
- See processor checkpoints and retries
- Inspect workflow output

### 2. Horizon (Queue Dashboard)
```
http://stash.test:8000/horizon
```
- Monitor job processing
- View failed jobs and retry
- Check queue metrics

### 3. Laravel Logs
```bash
tail -f storage/logs/laravel.log

# Or use Pail for prettier output:
php artisan pail
```

## 📁 File Structure

### Campaign Configuration
Location: Seeded via `database/seeders/CampaignSeeder.php`

```php
[
    'name' => 'ENF Electronic Signature',
    'slug' => 'e-signature',
    'allowed_mime_types' => ['application/pdf'], // PDF only
    'max_file_size_bytes' => 20971520, // 20MB
    'retention_days' => 2555, // 7 years
]
```

### Processors
- **EKycVerificationProcessor**: `app/Processors/EKycVerificationProcessor.php`
- **ElectronicSignatureProcessor**: `app/Processors/ElectronicSignatureProcessor.php`

### Dependencies
- **Trait**: `app/Models/Concerns/ProcessorDependencies.php`
- **Validation**: Automatic via `Processor->dependencies` array

## 🧪 Testing Scenarios

### Scenario 1: Happy Path (KYC Approved)

1. Upload PDF document
2. Complete KYC verification via link
3. HyperVerge approves → webhook received
4. Document automatically signed
5. Signed PDF with QR code available for download

**Expected Result**: ✅ Signed document with signer's ID photo + QR watermark

---

### Scenario 2: KYC Rejected

1. Upload PDF document
2. KYC verification fails (bad ID photo, face mismatch)
3. HyperVerge rejects → webhook received
4. Workflow stops at eKYC processor

**Expected Result**: ⚠️ Document not signed, error message in logs

---

### Scenario 3: Wrong File Type (Not PDF)

```bash
php artisan document:process ~/Downloads/image.jpg --campaign=e-signature
```

**Expected Result**: ❌ Error: "Only PDF documents are allowed for this campaign"

---

### Scenario 4: Dependency Violation (Manual Test)

Try to run ElectronicSignatureProcessor without eKYC first:

```bash
# This will fail:
php artisan tinker --execute="
use App\Models\Processor;
use App\Models\Document;

\$processor = Processor::where('slug', 'electronic-signature')->first();
\$document = Document::factory()->create(['mime_type' => 'application/pdf']);

// Should throw: RuntimeException
\$processor->assertDependenciesSatisfied('fake-job-id');
"
```

**Expected Result**: ❌ RuntimeException: "Processor 'electronic-signature' requires these processors to run first: ekyc-verification"

## 🔐 HyperVerge Configuration

### Environment Variables

Ensure these are set in `.env`:

```bash
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_APP_ID=your-app-id
HYPERVERGE_APP_KEY=your-app-key
HYPERVERGE_URL_WORKFLOW=workflow_2nQDNT

# For testing with existing transactions:
HYPERVERGE_FIXED_TRANSACTION_IDS=EKYC-1764773764-3863
```

### Webhook Setup

HyperVerge must send webhooks to:
```
POST https://stash.test/api/webhooks/hyperverge
```

**Handler**: `app/Jobs/ProcessHypervergeWebhook.php`

## 📊 Database Verification

### Check Campaign

```sql
-- In tenant database
SELECT name, slug, state 
FROM campaigns 
WHERE slug = 'e-signature';
```

### Check Processors

```sql
SELECT name, slug, category, dependencies 
FROM processors 
WHERE slug IN ('ekyc-verification', 'electronic-signature');
```

### Check Dependencies

```sql
SELECT slug, dependencies::text 
FROM processors 
WHERE dependencies IS NOT NULL;

-- Expected:
-- electronic-signature | ["ekyc-verification"]
```

## 🐛 Troubleshooting

### Issue: "Processor not found"

**Solution**: Run seeder
```bash
php artisan db:seed --class=ProcessorSeeder
```

### Issue: "Campaign not found"

**Solution**: Run seeder
```bash
php artisan db:seed --class=CampaignSeeder
```

### Issue: "Queue not processing"

**Solution**: Start queue worker
```bash
php artisan queue:work
# Or use dev server:
composer run dev
```

### Issue: "Workflow stuck at KYC"

**Cause**: Waiting for HyperVerge webhook  
**Solution**: Complete KYC via the onboarding link, or use fixed transaction IDs for testing

### Issue: "KYC not approved for transaction"

**Cause**: ElectronicSignatureProcessor requires approved KYC  
**Solution**: Ensure webhook handler updated Contact with `kyc_status = 'approved'`

## 📚 Related Documentation

- **Processor Dependencies**: `docs/PROCESSOR_DEPENDENCIES.md`
- **ENF Specification**: `ENF_ON_STASH.md`
- **WARP Guide**: `WARP.md`
- **Laravel Workflow**: `LARAVEL_WORKFLOW_ARCHITECTURE.md`

## ✨ Next Steps

After testing the ENF Electronic Signature campaign:

1. **Add remaining ENF processors**:
   - PaymentProcessor (from redeem-x)
   - DocumentHashingProcessor (SHA-256 + OpenTimestamps)
   - VideoSessionProcessor (Zoom/Twilio stub)

2. **Create ENF campaign templates**:
   - Basic Notarization
   - Bulk Notarization
   - Witnessed Document Signing

3. **Build UI for campaign templates**:
   - `Campaign::template()->get()` - List templates
   - `$campaign->duplicate($name, $slug)` - Clone template

## 🎉 Success Criteria

You'll know it's working when:

✅ Campaign `e-signature` exists in database  
✅ Both processors seeded with correct dependencies  
✅ Document upload triggers eKYC verification  
✅ KYC completion triggers document signing  
✅ Signed PDF has QR watermark + ID stamp  
✅ Verification URL works via QR scan  
✅ Dependencies enforced (can't sign without KYC)  

---

**Campaign is ready!** 🚀

Start testing with:
```bash
php artisan document:process ~/Downloads/contract.pdf --campaign=e-signature --wait --show-output
```
