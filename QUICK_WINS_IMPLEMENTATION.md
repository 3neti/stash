# 3 Quick Wins Implementation Guide

## Overview
This guide implements 3 quick wins for document processing robustness with full UI visibility:
1. **Progress Tracking** (4-6 hours) - Real-time progress bar and stage display
2. **Processor Hooks** (6-8 hours) - Time tracking for metrics
3. **Output Validation** (4-6 hours) - JSON Schema validation with job failure on invalid output

Total: 14-20 hours for massive robustness gains with visible UI improvements.

---

## Quick Win #1: Progress Tracking

### Goal
Users can see real-time progress as documents process through pipeline stages, with stage information and percentage complete.

### Architecture

```
DocumentProcessingPipeline
  ↓
  After each stage completes:
    ↓
    Update PipelineProgress record
    ↓
    Fire event (optional - for WebSocket later)
    ↓
    Document detail page queries /api/documents/{uuid}/progress
    ↓
    Vue component ProgressTracker.vue displays progress bar
```

### Implementation Steps

#### Step 1a: Create Migration for PipelineProgress Table

```php
// database/migrations/YYYY_MM_DD_HHMMSS_create_pipeline_progress_table.php
Schema::create('pipeline_progress', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->ulid('job_id')->index();
    $table->integer('stage_count');          // Total processors
    $table->integer('completed_stages');      // Processors done
    $table->float('percentage_complete');     // 0-100
    $table->string('current_stage')->nullable(); // e.g., "classification"
    $table->string('status');                 // queued, processing_stage_1_of_4, paused, failed, completed
    $table->integer('estimated_seconds_remaining')->nullable();
    $table->timestamps();
    $table->foreign('job_id')->references('id')->on('document_jobs')->cascadeOnDelete();
});

// Run: php artisan migrate
```

#### Step 1b: Create PipelineProgress Model

```php
<?php // app/Models/PipelineProgress.php
namespace App\Models;

use App\Tenancy\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PipelineProgress extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = [
        'job_id',
        'stage_count',
        'completed_stages',
        'percentage_complete',
        'current_stage',
        'status',
        'estimated_seconds_remaining',
    ];

    protected $casts = [
        'percentage_complete' => 'float',
        'stage_count' => 'integer',
        'completed_stages' => 'integer',
        'estimated_seconds_remaining' => 'integer',
    ];

    public function job()
    {
        return $this->belongsTo(DocumentJob::class, 'job_id');
    }
}
```

#### Step 1c: Update DocumentProcessingPipeline to Track Progress

In `app/Services/Pipeline/DocumentProcessingPipeline.php`, after `handleStageResult()` completes successfully:

```php
private function handleStageResult(
    DocumentJob $job,
    ProcessorExecution $execution,
    ProcessorResult $result,
    array $processorConfig
): bool {
    if ($result->isSuccess()) {
        // ... existing success logic ...
        
        // UPDATE PROGRESS HERE
        $totalStages = count($job->pipeline_instance['processors']);
        $completedStages = $job->current_processor_index;
        
        PipelineProgress::updateOrCreate(
            ['job_id' => $job->id],
            [
                'stage_count' => $totalStages,
                'completed_stages' => $completedStages,
                'percentage_complete' => ($completedStages / $totalStages) * 100,
                'current_stage' => $completedStages < $totalStages 
                    ? $job->pipeline_instance['processors'][$completedStages]['type'] 
                    : null,
                'status' => $completedStages < $totalStages
                    ? "processing_stage_{$completedStages + 1}_of_{$totalStages}"
                    : 'completed',
            ]
        );
        
        return true;
    } else {
        // Handle failure
        $this->failProcessing($job, $result->error);
        
        // Update progress as failed
        PipelineProgress::updateOrCreate(['job_id' => $job->id], [
            'status' => 'failed',
        ]);
        
        return false;
    }
}
```

Also update when job is first created:

```php
public function process(Document $document, Campaign $campaign): DocumentJob
{
    // ... existing code ...
    
    $totalStages = count($campaign->pipeline_config['processors'] ?? []);
    
    // Create initial progress record
    PipelineProgress::create([
        'job_id' => $job->id,
        'stage_count' => $totalStages,
        'completed_stages' => 0,
        'percentage_complete' => 0,
        'current_stage' => $totalStages > 0 ? $campaign->pipeline_config['processors'][0]['type'] : null,
        'status' => 'queued',
    ]);
    
    return $job;
}
```

#### Step 1d: Create API Endpoint for Progress

```php
<?php // app/Http/Controllers/DocumentProgressController.php
namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Response;

class DocumentProgressController extends Controller
{
    public function show(string $uuid): Response
    {
        $document = Document::where('uuid', $uuid)->firstOrFail();
        $job = $document->documentJob;
        
        if (!$job) {
            return response()->json([
                'status' => 'pending',
                'percentage_complete' => 0,
            ]);
        }
        
        $progress = $job->progress;
        
        return response()->json([
            'status' => $progress?->status ?? 'queued',
            'percentage_complete' => $progress?->percentage_complete ?? 0,
            'stage_count' => $progress?->stage_count ?? 0,
            'completed_stages' => $progress?->completed_stages ?? 0,
            'current_stage' => $progress?->current_stage,
            'estimated_seconds_remaining' => $progress?->estimated_seconds_remaining,
        ]);
    }
}

// routes/api.php - Add this route:
Route::middleware(['auth:sanctum'])->get('documents/{uuid}/progress', [DocumentProgressController::class, 'show']);
```

#### Step 1e: Create ProgressTracker.vue Component

```vue
<!-- resources/js/components/ProgressTracker.vue -->
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { Progress } from '@/components/ui/progress';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import axios from 'axios';

interface Props {
  documentUuid: string;
  pollInterval?: number;
}

const props = withDefaults(defineProps<Props>(), {
  pollInterval: 2000, // Poll every 2 seconds
});

interface ProgressData {
  status: string;
  percentage_complete: number;
  stage_count: number;
  completed_stages: number;
  current_stage: string | null;
  estimated_seconds_remaining: number | null;
}

const progress = ref<ProgressData | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
let pollInterval: NodeJS.Timeout | null = null;

const statusColors: Record<string, string> = {
  'queued': 'secondary',
  'completed': 'default',
  'failed': 'destructive',
};

function getStatusColor(status: string) {
  // Extract base status from "processing_stage_1_of_4"
  if (status.includes('processing')) return 'default';
  return statusColors[status] || 'secondary';
}

function formatStatus(status: string) {
  return status.split('_').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(' ');
}

async function fetchProgress() {
  try {
    const response = await axios.get(`/api/documents/${props.documentUuid}/progress`);
    progress.value = response.data;
    error.value = null;
  } catch (err) {
    error.value = 'Failed to fetch progress';
    console.error(err);
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchProgress();
  
  // Poll for updates
  pollInterval = setInterval(() => {
    if (progress.value?.status !== 'completed' && progress.value?.status !== 'failed') {
      fetchProgress();
    }
  }, props.pollInterval);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-lg flex justify-between items-center">
        <span>Processing Progress</span>
        <Badge 
          v-if="progress" 
          :variant="getStatusColor(progress.status)"
        >
          {{ formatStatus(progress.status) }}
        </Badge>
      </CardTitle>
    </CardHeader>
    <CardContent class="space-y-4">
      <!-- Error State -->
      <div v-if="error" class="text-sm text-destructive">
        {{ error }}
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-sm text-muted-foreground">
        Loading progress...
      </div>

      <!-- Progress Content -->
      <div v-else-if="progress" class="space-y-4">
        <!-- Progress Bar -->
        <div class="space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-muted-foreground">
              {{ progress.completed_stages }} of {{ progress.stage_count }} stages
            </span>
            <span class="font-medium">
              {{ Math.round(progress.percentage_complete) }}%
            </span>
          </div>
          <Progress :value="progress.percentage_complete" class="h-2" />
        </div>

        <!-- Current Stage -->
        <div v-if="progress.current_stage" class="space-y-1">
          <p class="text-sm text-muted-foreground">Current Stage</p>
          <p class="font-medium capitalize">
            {{ progress.current_stage }}
          </p>
        </div>

        <!-- ETA (if available) -->
        <div v-if="progress.estimated_seconds_remaining" class="space-y-1">
          <p class="text-sm text-muted-foreground">Estimated Time Remaining</p>
          <p class="font-medium">
            {{ progress.estimated_seconds_remaining }}s
          </p>
        </div>

        <!-- Completion Message -->
        <div v-if="progress.status === 'completed'" class="text-sm text-green-600">
          ✓ Processing complete!
        </div>

        <!-- Failure Message -->
        <div v-else-if="progress.status === 'failed'" class="text-sm text-destructive">
          ✗ Processing failed. Check logs for details.
        </div>
      </div>
    </CardContent>
  </Card>
</template>
```

#### Step 1f: Use ProgressTracker in Document Detail Page

In your `resources/js/pages/Documents/Show.vue` (or `campaigns/Show.vue`):

```vue
<script setup lang="ts">
import ProgressTracker from '@/components/ProgressTracker.vue';

interface Props {
  document: Document;
}
</script>

<template>
  <div class="space-y-6">
    <h1>{{ document.original_filename }}</h1>
    
    <!-- Add this component -->
    <ProgressTracker :document-uuid="document.uuid" />
    
    <!-- Rest of document detail page -->
  </div>
</template>
```

---

## Quick Win #2: Processor Hooks (Time Tracking)

### Goal
Track execution time for each processor to understand pipeline performance and identify bottlenecks.

### Architecture

```
ProcessorHookManager
  ↓
  Register TimeTrackingHook
  ↓
  Before each processor: hook->beforeExecution()
  ↓
  After each processor: hook->afterExecution() [calculate duration]
  ↓
  Store duration in ProcessorExecution.duration_ms
```

### Implementation Steps

#### Step 2a: Create ProcessorHook Interface

```php
<?php // app/Contracts/Processors/ProcessorHook.php
namespace App\Contracts\Processors;

use App\Models\ProcessorExecution;
use App\Processors\AbstractProcessor;
use Throwable;

interface ProcessorHook
{
    /**
     * Called before processor execution starts.
     */
    public function beforeExecution(
        AbstractProcessor $processor,
        string $processorId,
        array $config
    ): void;

    /**
     * Called after processor execution completes successfully.
     */
    public function afterExecution(
        AbstractProcessor $processor,
        ProcessorResult $result,
        ProcessorExecution $execution
    ): void;

    /**
     * Called when processor execution fails.
     */
    public function onFailure(
        AbstractProcessor $processor,
        ProcessorExecution $execution,
        Throwable $exception
    ): void;
}
```

#### Step 2b: Create ProcessorHookManager

```php
<?php // app/Services/Pipeline/ProcessorHookManager.php
namespace App\Services\Pipeline;

use App\Contracts\Processors\ProcessorHook;
use Illuminate\Support\Collection;

class ProcessorHookManager
{
    protected Collection $hooks;

    public function __construct()
    {
        $this->hooks = collect();
    }

    public function register(ProcessorHook $hook): void
    {
        $this->hooks->push($hook);
    }

    public function beforeExecution($processor, $processorId, array $config): void
    {
        $this->hooks->each(fn($hook) => $hook->beforeExecution($processor, $processorId, $config));
    }

    public function afterExecution($processor, $result, $execution): void
    {
        $this->hooks->each(fn($hook) => $hook->afterExecution($processor, $result, $execution));
    }

    public function onFailure($processor, $execution, $exception): void
    {
        $this->hooks->each(fn($hook) => $hook->onFailure($processor, $execution, $exception));
    }
}
```

#### Step 2c: Create TimeTrackingHook

```php
<?php // app/Services/Pipeline/Hooks/TimeTrackingHook.php
namespace App\Services\Pipeline\Hooks;

use App\Contracts\Processors\ProcessorHook;
use App\Data\Processors\ProcessorResult;
use App\Models\ProcessorExecution;
use Illuminate\Support\Facades\Log;
use Throwable;

class TimeTrackingHook implements ProcessorHook
{
    protected array $startTimes = [];

    public function beforeExecution($processor, $processorId, array $config): void
    {
        $this->startTimes[$processorId] = microtime(true);
    }

    public function afterExecution($processor, ProcessorResult $result, ProcessorExecution $execution): void
    {
        if (!isset($this->startTimes[$execution->processor_id])) {
            return;
        }

        $startTime = $this->startTimes[$execution->processor_id];
        $duration = (int) ((microtime(true) - $startTime) * 1000); // Convert to milliseconds

        $execution->update(['duration_ms' => $duration]);

        Log::info('Processor executed', [
            'processor_id' => $execution->processor_id,
            'duration_ms' => $duration,
            'tokens_used' => $result->tokensUsed,
        ]);

        unset($this->startTimes[$execution->processor_id]);
    }

    public function onFailure($processor, ProcessorExecution $execution, Throwable $exception): void
    {
        if (isset($this->startTimes[$execution->processor_id])) {
            $startTime = $this->startTimes[$execution->processor_id];
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $execution->update(['duration_ms' => $duration]);

            unset($this->startTimes[$execution->processor_id]);
        }
    }
}
```

#### Step 2d: Register Hook in AppServiceProvider

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    // ... existing code ...

    // Register processor hooks
    $hookManager = $this->app->make(ProcessorHookManager::class);
    $hookManager->register(new TimeTrackingHook());
}
```

#### Step 2e: Integrate Hooks into DocumentProcessingPipeline

In `app/Services/Pipeline/DocumentProcessingPipeline.php`:

```php
public function __construct(
    private ProcessorRegistry $registry,
    private ProcessorHookManager $hookManager,  // ADD THIS
) {}

public function executeNextStage(DocumentJob $job): bool
{
    // ... existing processor setup code ...

    try {
        // CALL BEFORE HOOK
        $this->hookManager->beforeExecution($processor, $processorId, $processorConfig['config'] ?? []);

        $result = $processor->handle(
            $job->document,
            $processorConfig['config'] ?? [],
            [
                'job_id' => $job->id,
                'processor_index' => $currentIndex,
                'previous_outputs' => $job->document->metadata['processor_outputs'] ?? [],
            ]
        );

        // CALL AFTER HOOK
        if ($result->isSuccess()) {
            $this->hookManager->afterExecution($processor, $result, $execution);
        }

        return $this->handleStageResult($job, $execution, $result, $processorConfig);
    } catch (Throwable $e) {
        // CALL FAILURE HOOK
        $this->hookManager->onFailure($processor, $execution, $e);

        $result = ProcessorResult::failed('Processor exception: ' . $e->getMessage());
        return $this->handleStageResult($job, $execution, $result, $processorConfig);
    }
}
```

#### Step 2f: Create ProcessingMetrics Component

```vue
<!-- resources/js/components/ProcessingMetrics.vue -->
<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Clock } from 'lucide-vue-next';

interface ProcessorExecution {
  processor_id: string;
  status: string;
  duration_ms: number | null;
  tokens_used: number | null;
  created_at: string;
}

interface Props {
  executions: ProcessorExecution[];
}

const props = defineProps<Props>();

function formatDuration(ms: number | null) {
  if (!ms) return '—';
  if (ms < 1000) return `${ms}ms`;
  return `${(ms / 1000).toFixed(2)}s`;
}

const totalDuration = computed(() => {
  return props.executions.reduce((sum, e) => sum + (e.duration_ms || 0), 0);
});
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="text-lg flex items-center gap-2">
        <Clock class="h-5 w-5" />
        Processing Metrics
      </CardTitle>
    </CardHeader>
    <CardContent>
      <div class="space-y-4">
        <!-- Total Duration -->
        <div class="flex justify-between items-center pb-4 border-b">
          <span class="text-muted-foreground">Total Time</span>
          <span class="font-medium">
            {{ formatDuration(totalDuration) }}
          </span>
        </div>

        <!-- Individual Processor Times -->
        <div v-if="executions.length > 0" class="space-y-2">
          <div v-for="execution in executions" :key="execution.processor_id" class="flex justify-between text-sm">
            <span class="text-muted-foreground capitalize">
              {{ execution.processor_id }} {{ execution.status === 'completed' ? '✓' : '✗' }}
            </span>
            <span class="font-mono">{{ formatDuration(execution.duration_ms) }}</span>
          </div>
        </div>
        <div v-else class="text-sm text-muted-foreground">
          No metrics available yet
        </div>
      </div>
    </CardContent>
  </Card>
</template>
```

---

## Quick Win #3: Output Validation

### Goal
Validate processor outputs against JSON Schema to catch errors early and fail entire job on invalid output.

### Implementation Steps

#### Step 3a: Update ProcessorInterface

```php
<?php // app/Contracts/Processors/ProcessorInterface.php
namespace App\Contracts\Processors;

interface ProcessorInterface
{
    public function handle(/* ... */): ProcessorResult;
    
    /**
     * Return JSON Schema for validation.
     * Returns null if no validation is needed.
     */
    public function getOutputSchema(): ?array;
}
```

#### Step 3b: Create JsonSchemaValidator

```php
<?php // app/Services/Validation/JsonSchemaValidator.php
namespace App\Services\Validation;

use InvalidArgumentException;

class JsonSchemaValidator
{
    /**
     * Validate data against JSON schema.
     * 
     * @throws InvalidArgumentException
     */
    public function validate(array $data, array $schema): void
    {
        if (!$this->matches($data, $schema)) {
            throw new InvalidArgumentException(
                'Data does not match schema: ' . json_encode($schema)
            );
        }
    }

    private function matches(mixed $data, array $schema): bool
    {
        $type = $schema['type'] ?? null;

        // Type checking
        match ($type) {
            'object' => $this->validateObject($data, $schema),
            'array' => $this->validateArray($data, $schema),
            'string' => is_string($data),
            'number', 'integer' => is_numeric($data),
            'boolean' => is_bool($data),
            'null' => $data === null,
            default => true,
        };

        // Check required fields for objects
        if ($type === 'object' && isset($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (!isset($data[$field])) {
                    return false;
                }
            }
        }

        return true;
    }

    private function validateObject(mixed $data, array $schema): bool
    {
        if (!is_array($data)) {
            return false;
        }

        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                if (isset($data[$property])) {
                    if (!$this->matches($data[$property], $propertySchema)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private function validateArray(mixed $data, array $schema): bool
    {
        if (!is_array($data)) {
            return false;
        }

        if (isset($schema['items'])) {
            foreach ($data as $item) {
                if (!$this->matches($item, $schema['items'])) {
                    return false;
                }
            }
        }

        return true;
    }
}
```

#### Step 3c: Update AbstractProcessor with Schema

```php
<?php // app/Processors/AbstractProcessor.php
namespace App\Processors;

abstract class AbstractProcessor implements ProcessorInterface
{
    public function getOutputSchema(): ?array
    {
        // Override in subclasses
        return null;
    }

    // ... rest of implementation
}
```

#### Step 3d: Update DocumentProcessingPipeline to Validate Output

In `app/Services/Pipeline/DocumentProcessingPipeline.php`, in `handleStageResult()`:

```php
private function handleStageResult(
    DocumentJob $job,
    ProcessorExecution $execution,
    ProcessorResult $result,
    array $processorConfig
): bool {
    if ($result->isSuccess()) {
        // VALIDATE OUTPUT
        $processor = $this->registry->get($processorId);
        $schema = $processor->getOutputSchema();

        if ($schema !== null) {
            try {
                $validator = new JsonSchemaValidator();
                $validator->validate($result->output, $schema);
            } catch (InvalidArgumentException $e) {
                // Validation failed - treat as processor failure
                Log::error('Processor output validation failed', [
                    'processor' => $processorId,
                    'error' => $e->getMessage(),
                ]);

                $execution->update([
                    'status' => 'failed',
                    'error_message' => 'Output validation failed: ' . $e->getMessage(),
                ]);

                // FAIL ENTIRE JOB (as per your requirement)
                $this->failProcessing(
                    $job,
                    'Processor output validation failed: ' . $e->getMessage()
                );

                return false;
            }
        }

        // ... rest of success logic ...
        return true;
    }
    
    // ... failure logic ...
}
```

#### Step 3e: Add Schemas to Existing Processors

Example - OcrProcessor:

```php
class OcrProcessor extends AbstractProcessor
{
    public function getOutputSchema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'language_detected' => ['type' => 'string'],
                'pages' => ['type' => 'integer'],
            ],
            'required' => ['text', 'confidence'],
        ];
    }
}
```

---

## Testing All 3 Quick Wins

### Integration Test

```php
<?php // tests/Feature/DeadDrop/QuickWinsTest.php
namespace Tests\Feature\DeadDrop;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\PipelineProgress;
use App\Tenancy\TenantContext;

test('progress tracking updates during pipeline execution', function () {
    // Setup
    $tenant = Tenant::factory()->create();
    $campaign = Campaign::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_config' => [...],
    ]);
    
    $document = null;
    TenantContext::run($tenant, function () use ($campaign, &$document) {
        $document = Document::factory()->create(['campaign_id' => $campaign->id]);
        
        // Trigger pipeline
        $pipeline = app(DocumentProcessingPipeline::class);
        $job = $pipeline->process($document, $campaign);
        
        // Initial progress
        $progress = PipelineProgress::where('job_id', $job->id)->first();
        expect($progress)->not->toBeNull();
        expect($progress->percentage_complete)->toBe(0);
        expect($progress->status)->toBe('queued');
        
        // Execute queue job
        ProcessDocumentJob::dispatch($job->id)->run();
        
        // Final progress
        $progress->refresh();
        expect($progress->percentage_complete)->toBeGreaterThan(0);
    });
});

test('processor hooks track execution time', function () {
    // Similar setup
    // Verify ProcessorExecution.duration_ms is populated
    // Verify > 0 milliseconds
});

test('output validation fails job on invalid output', function () {
    // Setup mock processor with invalid output
    // Verify job is marked as failed
    // Verify error message contains "validation failed"
});
```

---

## UI Verification Checklist

- [ ] Progress tracker appears on document detail page
- [ ] Progress bar updates as processors execute
- [ ] Current stage displays correctly
- [ ] Percentage shows 0-100%
- [ ] Status badge changes from "queued" → "processing_stage_X_of_Y" → "completed"
- [ ] Processing metrics component shows execution times
- [ ] Times are in milliseconds or seconds format
- [ ] API endpoint `/api/documents/{uuid}/progress` returns correct data
- [ ] Validation errors are displayed properly
- [ ] Failed jobs show in UI with error message

---

## Implementation Order

1. **Step 1: Progress Tracking** (4-6h)
   - Migration + Model + Pipeline updates + API endpoint + Vue component
   - Test with manual document upload

2. **Step 2: Processor Hooks** (6-8h)
   - Hook interface + Manager + TimeTrackingHook + AppServiceProvider + Pipeline integration + Metrics component
   - Verify duration_ms is populated

3. **Step 3: Output Validation** (4-6h)
   - Validator + ProcessorInterface update + AbstractProcessor update + Pipeline integration + Add schemas to processors
   - Test with mock invalid output

4. **Step 4: Testing & UI Polish** (2-4h)
   - Write integration tests
   - Polish UI components
   - Verify all 3 features work together

---

## Success Criteria

✅ Progress tracking shows real-time updates with percentage and stage info
✅ Processing metrics display execution time for each processor
✅ Output validation fails jobs with invalid processor output
✅ All UI components render correctly
✅ Integration tests pass
✅ No breaking changes to existing functionality
✅ User can see processing in real-time on the web
