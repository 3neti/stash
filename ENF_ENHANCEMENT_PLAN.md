# ENF Enhancement Plan

## Overview

Extend the Electronic Signature Framework (ENF) with:
- **Blockchain timestamping** for signed documents using OpenTimestamps
- **SMS notification Activity** in Laravel Workflow

## Phase 1: Blockchain Timestamping Integration

### Implementation

**Pattern**: Copy from kwyc-campaign's proven implementation

### Files to Create

#### 1. Blockchain Timestamp Processor

**File**: `app/Services/Processors/Timestamp/BlockchainTimestampProcessor.php`

- Wraps OpenTimestamps API
- Returns `ProcessorResult` with timestamp metadata
- Free Bitcoin blockchain timestamping via OpenTimestamps
- SHA-256 hash of signed document
- Base64-encoded OTS proof stored in database
- Status tracking: `pending`, `confirmed`, `failed`

#### 2. Configuration File

**File**: `config/timestamp.php`

- OpenTimestamps configuration
- Calendar server URL: `https://alice.btc.calendar.opentimestamps.org`
- Timeout settings

#### 3. Database Migration

Add columns to `documents` table:
- `timestamp_proof` (text) - Base64-encoded OTS proof
- `timestamp_hash` (string) - SHA-256 hash
- `timestamp_status` (enum: pending/confirmed/failed)
- `timestamp_block_height` (integer, nullable)
- `timestamp_confirmed_at` (timestamp, nullable)

### Integration Point

Add as optional step in Electronic Signature Processor after document signing:

```php
// In ElectronicSignatureProcessor
if ($config['enable_blockchain_timestamp'] ?? false) {
    $timestamp = BlockchainTimestampProcessor::process($signedDocument);
}
```

### Testing

- Unit test: `BlockchainTimestampProcessorTest`
- Feature test: End-to-end signature + timestamp
- Manual test: Verify proof on opentimestamps.org

## Phase 2: SMS Notification Activity

### Implementation

**Pattern**: Laravel Workflow Activity for document notifications

### File to Create

**File**: `app/Workflows/Activities/SendNotificationActivity.php`

#### Activity Structure

```php
class SendNotificationActivity extends Activity
{
    public $tries = 3;
    public $timeout = 60;

    public function execute(
        string $documentJobId,
        string $tenantId,
        array $notificationConfig
    ): array {
        // 1. Initialize tenant context
        // 2. Load DocumentJob
        // 3. Get mobile from campaign settings
        // 4. Send via anonymous notification
        // 5. Return result
    }
}
```

### Integration Point

Add to `DocumentProcessingWorkflow` after completion:

```php
$notificationResult = yield ActivityStub::make(
    SendNotificationActivity::class,
    $documentJobId,
    $tenantId,
    $campaign->notification_settings
);
```

### Configuration

**Campaign `notification_settings`** structure:

```json
{
  "channels": ["database", "sms"],
  "sms_provider": "txtcmdr",
  "sms_mobile": "09173011987",
  "sms_message_template": "Document processed: {filename}"
}
```

### Testing

- Unit test: `SendNotificationActivity` with mocked SMS
- Feature test: Workflow with notification step
- Integration test: Actual SMS delivery (manual)

## Phase 3: Verification Page Enhancement

### Updates to Verify.vue

Add blockchain timestamp section:
- Timestamp status badge (pending/confirmed)
- Block height and confirmation count
- Link to blockchain explorer
- Button to verify on opentimestamps.org

### Updates to DocumentVerificationController

Include timestamp data in response:

```php
'timestamp' => [
    'hash' => $document->timestamp_hash,
    'status' => $document->timestamp_status,
    'block_height' => $document->timestamp_block_height,
    'confirmed_at' => $document->timestamp_confirmed_at,
    'proof' => $document->timestamp_proof, // For download
]
```

## Environment Variables

Add to `.env`:

```bash
# Blockchain Timestamping
TIMESTAMP_ENABLED=true
OPENTIMESTAMPS_URL=https://alice.btc.calendar.opentimestamps.org
TIMESTAMP_TIMEOUT=10
```

## Success Criteria

### Blockchain Timestamping

- ✓ Signed documents automatically timestamped
- ✓ Timestamp proof stored in database
- ✓ Status tracking (pending → confirmed)
- ✓ Verification page displays timestamp info
- ✓ Independent verification on opentimestamps.org

### SMS Notification Activity

- ✓ Activity executes in workflow
- ✓ Tenant context properly initialized
- ✓ SMS sent via anonymous notification
- ✓ Configurable per campaign
- ✓ Error handling and retries
- ✓ Logs all notification attempts

## Future Enhancements (Option 2)

- Multiple signers per document
- Signature approval workflows
- Signature expiration/revocation
- Custom signature positions
