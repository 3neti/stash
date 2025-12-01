# 3 Quick Wins - UI Preview

## What Users Will See

### 1️⃣ Progress Tracking - Real-Time Progress Bar

When a user uploads a document and views the document detail page:

```
┌────────────────────────────────────────────────┐
│  📄 invoice.pdf                                │
├────────────────────────────────────────────────┤
│                                                │
│  Processing Progress              [Processing] │
│                                                │
│  2 of 4 stages                            50%  │
│  ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░  │
│                                                │
│  Current Stage: classification                │
│                                                │
│  Estimated Time Remaining: 15s                │
│                                                │
└────────────────────────────────────────────────┘
```

**Features**:
- Real-time progress bar (0-100%)
- Completed stages / Total stages counter
- Current processor name (e.g., "classification")
- Status badge (Queued → Processing Stage 2 of 4 → Completed)
- Estimated time remaining (with future ETA calculation)
- Auto-updates every 2 seconds via API polling
- Shows completion message when done

**API Endpoint**:
```
GET /api/documents/{uuid}/progress

Response:
{
  "status": "processing_stage_2_of_4",
  "percentage_complete": 50,
  "stage_count": 4,
  "completed_stages": 2,
  "current_stage": "classification",
  "estimated_seconds_remaining": 15
}
```

---

### 2️⃣ Processor Hooks - Processing Metrics Display

Below the progress tracker, shows execution time for each processor:

```
┌────────────────────────────────────────────────┐
│  ⏱️  Processing Metrics                         │
├────────────────────────────────────────────────┤
│                                                │
│  Total Time                           2.34s   │
│  ──────────────────────────────────────────── │
│                                                │
│  ocr ✓                                 1200ms  │
│  classification ✓                       850ms  │
│  extraction ✓                           290ms  │
│  validation ⏳                          (pending)│
│                                                │
└────────────────────────────────────────────────┘
```

**Features**:
- Tracks execution time for each processor
- Shows status icon (✓ completed, ✗ failed, ⏳ pending)
- Formats time (< 1s = ms, > 1s = seconds)
- Calculates total processing time
- Updates in real-time

**How it Works**:
```
ProcessorHookManager
  ↓
  TimeTrackingHook.beforeExecution() → Record start time
  ↓
  Processor executes...
  ↓
  TimeTrackingHook.afterExecution() → Calculate duration, save to DB
  ↓
  ProcessorExecution.duration_ms = 1234
```

---

### 3️⃣ Output Validation - Error Handling

When a processor returns invalid output:

```
┌────────────────────────────────────────────────┐
│  📄 invoice.pdf                                │
├────────────────────────────────────────────────┤
│                                                │
│  Processing Progress                  [Failed]│
│                                                │
│  ✗ Processing failed. Check logs for details. │
│                                                │
│  Error Details:                                │
│  ───────────────────────────────────────────  │
│  Stage: Classification (3 of 4)                │
│  Error: Output validation failed               │
│  Required field 'confidence' was missing       │
│                                                │
│  [Retry]  [Skip Processor]  [View Logs]       │
│                                                │
└────────────────────────────────────────────────┘
```

**Features**:
- Failed badge in status
- Clear error message with validation details
- Shows which processor failed
- Shows required fields that were missing
- Action buttons for retry/skip/logs

**How it Works**:
```
Processor returns: {"text": "Invoice #123"}
  ↓
Pipeline validates against schema:
{
  "type": "object",
  "properties": {
    "text": {"type": "string"},
    "confidence": {"type": "number"}  ← MISSING!
  },
  "required": ["text", "confidence"]
}
  ↓
Validation fails
  ↓
Entire job marked as FAILED (per your requirement)
  ↓
Error logged and displayed to user
```

---

## Complete User Flow

### Scenario: Upload a PDF, See Progress in Real-Time

1. **User uploads invoice.pdf**
   ```
   POST /api/campaigns/01abc.../documents
   → File stored
   → Document created
   → Pipeline initialized (4 processors: OCR, Classification, Extraction, Validation)
   → Job queued
   ```

2. **User navigates to document detail page**
   ```
   GET /documents/invoice-uuid
   → ProgressTracker component loads
   → Polls /api/documents/invoice-uuid/progress every 2s
   ```

3. **Real-time updates as processing happens**
   ```
   Initial: 0% complete (Queued)
   ↓ 2s: OCR completes → 25% (Processing stage 2 of 4)
   ↓ 5s: Classification completes → 50% (Processing stage 3 of 4)
   ↓ 8s: Extraction completes → 75% (Processing stage 4 of 4)
   ↓ 10s: Validation completes → 100% (✓ Processing complete!)
   ```

4. **Metrics visible throughout**
   ```
   OCR: 1234ms
   Classification: 890ms
   Extraction: 456ms
   Validation: 234ms
   ────────────
   Total: 2814ms
   ```

---

## Technical Implementation Summary

### Database Changes
```
✓ Create pipeline_progress table
  - job_id, stage_count, completed_stages, percentage_complete
  - current_stage, status, estimated_seconds_remaining
```

### Backend Code
```
✓ PipelineProgress model
✓ DocumentProgressController with /api/documents/{uuid}/progress endpoint
✓ Update DocumentProcessingPipeline to track progress after each stage
✓ ProcessorHook interface for extensibility
✓ ProcessorHookManager for hook orchestration
✓ TimeTrackingHook implementation
✓ JsonSchemaValidator for output validation
✓ Update AbstractProcessor with getOutputSchema()
✓ Integration in DocumentProcessingPipeline
```

### Frontend Components
```
✓ ProgressTracker.vue - Main progress bar component
✓ ProcessingMetrics.vue - Time tracking display
✓ Update document detail pages to include progress tracking
```

### API Endpoints
```
✓ GET /api/documents/{uuid}/progress - Returns real-time progress data
```

---

## Benefits Summary

| Feature | Benefit |
|---------|---------|
| **Progress Tracking** | Users see real-time feedback, no more wondering "is it working?" |
| **Processor Hooks** | Identify bottlenecks, optimize slow processors |
| **Output Validation** | Catch processor errors immediately, fail fast instead of cascading failures |

---

## Timeline

- **Progress Tracking**: 4-6 hours
- **Processor Hooks**: 6-8 hours  
- **Output Validation**: 4-6 hours
- **Testing & Polish**: 2-4 hours
- **Total: 14-20 hours**

---

## Visibility in UI

✅ Progress bar with percentage updates every 2 seconds
✅ Current stage displays processor name
✅ Execution time metrics per processor
✅ Total processing time calculated
✅ Status badges (queued → processing → completed/failed)
✅ Error messages with validation details
✅ All visible on document detail page without leaving to check logs
