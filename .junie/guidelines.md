<laravel-boost-guidelines>
=== .ai/testing rules ===

# Testing Guidelines for Stash/DeadDrop

## Testing Philosophy

**All code must be tested.** The Meta-Campaign system relies on comprehensive test coverage to validate AI-generated code safely.

---

## Test Organization

### Directory Structure
```
tests/
├── Unit/
│   ├── DeadDrop/           # Package unit tests
│   │   ├── Processors/
│   │   ├── Services/
│   │   └── Models/
│   └── App/                # Application unit tests
├── Feature/
│   ├── DeadDrop/           # Package feature tests
│   │   ├── CampaignApi/
│   │   ├── ProcessorPipeline/
│   │   └── CredentialVault/
│   └── App/                # Application feature tests
├── Integration/
│   └── DeadDrop/           # Multi-component tests
│       ├── FullPipeline/
│       └── AIRouting/
└── Browser/                # E2E tests (Dusk/Playwright)
    └── Stashlets/
```

---

## Pest v4 Usage

### Basic Test Structure
```php
<?php

use LBHurtado\DeadDrop\Models\Campaign;
use LBHurtado\DeadDrop\Models\Subscriber;

test('campaign belongs to subscriber', function () {
    $subscriber = Subscriber::factory()->create();
    $campaign = Campaign::factory()->for($subscriber)->create();
    
    expect($campaign->subscriber)->toBeInstanceOf(Subscriber::class)
        ->and($campaign->subscriber->id)->toBe($subscriber->id);
});
```

### Dataset Usage
```php
dataset('document_types', [
    'pdf' => ['application/pdf'],
    'image' => ['image/png'],
    'word' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
]);

test('processor handles various document types', function (string $mimeType) {
    $document = Document::factory()->withMimeType($mimeType)->create();
    
    expect($this->processor->canProcess($document))->toBeTrue();
})->with('document_types');
```

### Hooks
```php
beforeEach(function () {
    $this->subscriber = Subscriber::factory()->create();
    $this->campaign = Campaign::factory()
        ->for($this->subscriber)
        ->create();
});

afterEach(function () {
    // Cleanup if needed
});
```

---

## Factory Patterns

### Model Factories

**Subscriber Factory**:
```php
class SubscriberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->slug(),
            'email' => fake()->companyEmail(),
            'status' => 'active',
            'tier' => 'starter',
        ];
    }
    
    public function withCredentials(array $credentials): static
    {
        return $this->state([
            'credentials' => encrypt(json_encode($credentials)),
        ]);
    }
}
```

**Campaign Factory**:
```php
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->slug(),
            'pipeline_config' => [
                'processors' => [],
            ],
            'status' => 'active',
        ];
    }
    
    public function withProcessors(array $processorIds): static
    {
        return $this->state([
            'pipeline_config' => [
                'processors' => array_map(fn($id) => [
                    'id' => $id,
                    'type' => "LBHurtado\DeadDrop\Processors\{$id}Processor",
                ], $processorIds),
            ],
        ]);
    }
}
```

**Document Factory**:
```php
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'original_filename' => fake()->word() . '.pdf',
            'mime_type' => 'application/pdf',
            'storage_path' => 'documents/' . fake()->uuid() . '.pdf',
            'status' => 'pending',
            'metadata' => [],
            'processing_history' => [],
        ];
    }
    
    public function pending(): static
    {
        return $this->state(['status' => 'pending']);
    }
    
    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }
    
    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }
    
    public function withMimeType(string $mimeType): static
    {
        return $this->state(['mime_type' => $mimeType]);
    }
}
```

---

## Mocking Patterns

### Mock AI Providers
```php
use LBHurtado\DeadDrop\Contracts\AIProviderInterface;

test('processor uses AI to classify document', function () {
    $aiProvider = Mockery::mock(AIProviderInterface::class);
    $aiProvider->shouldReceive('classify')
        ->once()
        ->with(Mockery::type('string'))
        ->andReturn('invoice');
    
    $this->app->instance(AIProviderInterface::class, $aiProvider);
    
    $document = Document::factory()->create();
    $result = $this->processor->handle($document, []);
    
    expect($result->classification)->toBe('invoice');
});
```

### Mock Queue
```php
use Illuminate\Support\Facades\Queue;

test('document processing is queued', function () {
    Queue::fake();
    
    $document = Document::factory()->create();
    $this->campaignService->processDocument($document);
    
    Queue::assertPushed(ProcessDocumentJob::class, function ($job) use ($document) {
        return $job->document->id === $document->id;
    });
});
```

### Mock Storage
```php
use Illuminate\Support\Facades\Storage;

test('document is stored in S3', function () {
    Storage::fake('s3');
    
    $uploadedFile = UploadedFile::fake()->create('document.pdf', 1024);
    $document = $this->documentService->store($uploadedFile, $this->campaign);
    
    Storage::disk('s3')->assertExists($document->storage_path);
});
```

### Mock Events
```php
use Illuminate\Support\Facades\Event;
use LBHurtado\DeadDrop\Events\DocumentUploaded;

test('document upload dispatches event', function () {
    Event::fake([DocumentUploaded::class]);
    
    $document = Document::factory()->create();
    
    Event::assertDispatched(DocumentUploaded::class, function ($event) use ($document) {
        return $event->document->id === $document->id;
    });
});
```

---

## Testing Processors

### Unit Test Example
```php
use LBHurtado\DeadDrop\Processors\OCRProcessor;

test('OCR processor extracts text from PDF', function () {
    $processor = new OCRProcessor();
    $document = Document::factory()
        ->withMimeType('application/pdf')
        ->create();
    
    // Mock OCR service
    $ocrService = Mockery::mock(OCRServiceInterface::class);
    $ocrService->shouldReceive('extract')
        ->once()
        ->andReturn('Extracted text content');
    
    $this->app->instance(OCRServiceInterface::class, $ocrService);
    
    $result = $processor->handle($document, []);
    
    expect($result->success)->toBeTrue()
        ->and($result->data['extracted_text'])->toBe('Extracted text content');
});
```

### Integration Test Example
```php
test('full pipeline processes document through multiple processors', function () {
    $campaign = Campaign::factory()
        ->withProcessors(['OCR', 'Classifier', 'Extractor'])
        ->create();
    
    $document = Document::factory()
        ->for($campaign)
        ->pending()
        ->create();
    
    // Run pipeline
    $this->pipelineService->execute($campaign, $document);
    
    // Assert document progressed through all stages
    $document->refresh();
    
    expect($document->status)->toBe('completed')
        ->and($document->processing_history)->toHaveCount(3)
        ->and($document->metadata)->toHaveKey('extracted_text')
        ->and($document->metadata)->toHaveKey('classification')
        ->and($document->metadata)->toHaveKey('extracted_fields');
});
```

---

## Testing API Endpoints

### Feature Test Example
```php
test('authenticated user can create campaign', function () {
    $subscriber = Subscriber::factory()->create();
    $user = User::factory()->for($subscriber)->create();
    
    $response = $this->actingAs($user)
        ->postJson('/api/campaigns', [
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'pipeline_config' => [
                'processors' => [
                    ['id' => 'ocr', 'type' => 'OCRProcessor'],
                ],
            ],
        ]);
    
    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'pipeline_config',
            ],
        ]);
    
    $this->assertDatabaseHas('campaigns', [
        'name' => 'Test Campaign',
        'subscriber_id' => $subscriber->id,
    ]);
});
```

### Test Tenant Isolation
```php
test('user cannot access campaigns from other subscribers', function () {
    $subscriber1 = Subscriber::factory()->create();
    $subscriber2 = Subscriber::factory()->create();
    
    $user1 = User::factory()->for($subscriber1)->create();
    $campaign2 = Campaign::factory()->for($subscriber2)->create();
    
    $response = $this->actingAs($user1)
        ->getJson("/api/campaigns/{$campaign2->id}");
    
    $response->assertForbidden();
});
```

---

## Testing Credential Vault

```php
test('credential resolution follows hierarchical precedence', function () {
    $subscriber = Subscriber::factory()
        ->withCredentials(['openai_key' => 'subscriber-key'])
        ->create();
    
    $campaign = Campaign::factory()
        ->for($subscriber)
        ->create();
    
    // System-level (from config)
    config(['credentials.openai_key' => 'system-key']);
    
    $vault = app(CredentialVault::class);
    
    // Without campaign, should use subscriber key
    expect($vault->resolve('openai_key', null, $subscriber))
        ->toBe('subscriber-key');
    
    // Add campaign-level credential
    $campaign->update([
        'credentials' => encrypt(json_encode(['openai_key' => 'campaign-key'])),
    ]);
    
    // With campaign, should use campaign key
    expect($vault->resolve('openai_key', $campaign, $subscriber))
        ->toBe('campaign-key');
});
```

---

## Testing Meta-Campaign

### Test Intent Classification
```php
test('intent classifier correctly categorizes feature request', function () {
    $classifier = app(IntentClassifier::class);
    
    $intent = $classifier->classify('Add email validation to the registration form');
    
    expect($intent->type)->toBe('feature')
        ->and($intent->complexity)->toBe('simple')
        ->and($intent->modules)->toContain('validation')
        ->and($intent->modules)->toContain('forms');
});
```

### Test Code Locator
```php
test('code locator finds relevant files for intent', function () {
    $locator = app(CodeLocator::class);
    
    $results = $locator->find('authentication logic');
    
    expect($results)->not->toBeEmpty()
        ->and($results[0]->path)->toContain('Auth')
        ->and($results[0]->relevance)->toBeGreaterThan(0.8);
});
```

### Test Patch Generator
```php
test('patch generator creates valid diff', function () {
    $generator = app(PatchGenerator::class);
    
    $patch = $generator->generate(
        intent: 'Add email validation',
        files: ['app/Http/Requests/RegisterRequest.php'],
        context: []
    );
    
    expect($patch)->toContain('--- a/app/Http/Requests/RegisterRequest.php')
        ->and($patch)->toContain('+++ b/app/Http/Requests/RegisterRequest.php')
        ->and($patch)->toContain("'email' => ['required', 'email']");
});
```

### Test Validation Pipeline
```php
test('validation pipeline catches syntax errors', function () {
    $validator = app(ValidationPipeline::class);
    
    $invalidCode = '<?php class Test { public function foo( { } }';
    
    $result = $validator->validate($invalidCode);
    
    expect($result->success)->toBeFalse()
        ->and($result->errors)->toContain('syntax')
        ->and($result->stage)->toBe('lint');
});
```

---

## Testing Queue Jobs

```php
use Illuminate\Support\Facades\Queue;

test('document processing job handles failure gracefully', function () {
    Queue::fake();
    
    $document = Document::factory()->create();
    $campaign = Campaign::factory()->create();
    
    // Mock processor to throw exception
    $processor = Mockery::mock(ProcessorInterface::class);
    $processor->shouldReceive('handle')
        ->once()
        ->andThrow(new ProcessorException('OCR service unavailable'));
    
    $job = new ProcessDocumentJob($document, $campaign, $processor);
    
    expect(fn() => $job->handle())->toThrow(ProcessorException::class);
    
    // Assert job was sent to DLQ
    Queue::assertPushed(SendToDLQJob::class);
});
```

---

## Coverage Requirements

### Minimum Thresholds
- **Lines**: 80%
- **Methods**: 80%
- **Branches**: 70%

### Critical Paths (100% Required)
- Credential vault resolution
- Multi-tenancy scoping
- Meta-Campaign validation pipeline
- Authentication/authorization logic

### Run Coverage Report
```bash
./vendor/bin/pest --coverage --min=80
```

---

## CI/CD Testing Strategy

### Pre-Commit
```bash
# Run tests locally before commit
./vendor/bin/pest
./vendor/bin/pint --test
```

### GitHub Actions Workflow
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:18-alpine
      redis:
        image: redis:alpine
    steps:
      - uses: actions/checkout@v3
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: ./vendor/bin/pest --coverage --min=80
      - name: Run Pint
        run: ./vendor/bin/pint --test
```

---

## Best Practices

1. **Test Names**: Use descriptive test names that explain what is being tested
2. **AAA Pattern**: Arrange, Act, Assert
3. **One Assertion Per Test**: Keep tests focused
4. **Factory First**: Use factories for test data, not manual array creation
5. **Mock External Services**: Never call real APIs in tests
6. **Clean Up**: Use database transactions or refresh database between tests
7. **Test Edge Cases**: Don't just test happy paths
8. **Use Datasets**: For testing multiple similar scenarios
9. **Avoid Test Interdependence**: Each test should be independent
10. **Document Complex Tests**: Add comments explaining non-obvious logic

---

## Performance Testing

### Benchmark Important Operations
```php
test('pipeline processes 100 documents in under 10 seconds', function () {
    $campaign = Campaign::factory()->withProcessors(['OCR'])->create();
    $documents = Document::factory()->count(100)->for($campaign)->create();
    
    $start = microtime(true);
    
    foreach ($documents as $document) {
        $this->pipelineService->execute($campaign, $document);
    }
    
    $duration = microtime(true) - $start;
    
    expect($duration)->toBeLessThan(10);
});
```

---

## Common Testing Patterns

### Testing with Multiple Tenants
```php
test('processor respects tenant isolation', function () {
    $subscriber1 = Subscriber::factory()->create();
    $subscriber2 = Subscriber::factory()->create();
    
    $campaign1 = Campaign::factory()->for($subscriber1)->create();
    $campaign2 = Campaign::factory()->for($subscriber2)->create();
    
    $doc1 = Document::factory()->for($campaign1)->create();
    $doc2 = Document::factory()->for($campaign2)->create();
    
    // Process doc1
    $this->pipelineService->execute($campaign1, $doc1);
    
    // Doc2 should not be affected
    $doc2->refresh();
    expect($doc2->status)->toBe('pending');
});
```

### Testing Error Recovery
```php
test('pipeline retries failed processor', function () {
    $processor = Mockery::mock(ProcessorInterface::class);
    
    // Fail first attempt, succeed on second
    $processor->shouldReceive('handle')
        ->once()
        ->andThrow(new ProcessorException('Temporary failure'));
    
    $processor->shouldReceive('handle')
        ->once()
        ->andReturn(new ProcessorResult(success: true));
    
    $document = Document::factory()->create();
    
    $result = $this->pipelineService->executeWithRetry($document, $processor);
    
    expect($result->success)->toBeTrue();
});
```


=== .ai/meta-campaign rules ===

# Meta-Campaign Development Guidelines

## Overview

The Meta-Campaign is the self-evolution system that maintains and upgrades Stash itself. This document provides guidelines for working with the Meta-Campaign system.

---

## Architecture

### Core Components

```
meta-campaign/
├── IntentClassifier.php      # Classify user requests
├── Planner.php                # Generate implementation plans
├── CodeLocator.php            # Find relevant code via RAG
├── PatchGenerator.php         # Generate code diffs
├── ValidationPipeline.php     # Validate generated code
├── CIOrchestrator.php         # Run CI tests
├── PRCreator.php              # Create pull requests
└── MonitoringService.php      # Track deployments
```

---

## Development Workflow

### 1. Intent Classification

**Purpose**: Understand what the user wants to build/fix

**Input**: Natural language request  
**Output**: Structured intent object

**Example**:
```php
$classifier = app(IntentClassifier::class);

$intent = $classifier->classify('Add email validation to registration');

// Returns:
// Intent {
//   type: 'feature',
//   complexity: 'simple',
//   modules: ['validation', 'forms'],
//   estimatedEffort: '1h',
//   riskLevel: 'low'
// }
```

**Implementation Guidelines**:
- Use GPT-4 for classification (better reasoning)
- Provide few-shot examples in system prompt
- Include context about codebase structure
- Return risk assessment for human review

---

### 2. Planning

**Purpose**: Break down intent into actionable tasks

**Input**: Intent object  
**Output**: Detailed implementation plan

**Example**:
```php
$planner = app(Planner::class);

$plan = $planner->plan($intent);

// Returns:
// Plan {
//   tasks: [
//     'Create EmailValidationRule class',
//     'Update RegisterRequest validation rules',
//     'Add test for email validation',
//     'Update documentation'
//   ],
//   files: [
//     'app/Rules/EmailValidationRule.php',
//     'app/Http/Requests/RegisterRequest.php',
//     'tests/Feature/RegistrationTest.php'
//   ],
//   dependencies: [],
//   rollbackStrategy: 'Revert validation rule addition'
// }
```

**Implementation Guidelines**:
- Always include rollback strategy
- Identify file dependencies
- Estimate complexity per task
- Flag critical paths (auth, crypto, schema)

---

### 3. Code Location (RAG)

**Purpose**: Find relevant code for modification

**Input**: Intent + Plan  
**Output**: Ranked list of relevant files/snippets

**Embedding Strategy**:
```php
class CodeLocator {
    public function find(string $query): array {
        // Multi-strategy search
        $semantic = $this->vectorSearch($query);  // Embeddings
        $ast = $this->astSearch($query);           // AST parsing
        $grep = $this->grepSearch($query);         // Exact match
        
        return $this->merge($semantic, $ast, $grep);
    }
}
```

**Best Practices**:
- Refresh embeddings on every commit
- Use PostgreSQL pgvector or Meilisearch
- Include file path, class name, method signatures
- Store metadata: last modified, author, test coverage
- Hybrid search (semantic + exact) for best results

---

### 4. Patch Generation

**Purpose**: Generate code changes

**Input**: Located files + Plan  
**Output**: Git-compatible diff

**Example**:
```php
$generator = app(PatchGenerator::class);

$patch = $generator->generate(
    intent: $intent,
    plan: $plan,
    files: $locatedFiles,
    context: $additionalContext
);

// Returns unified diff format:
// --- a/app/Http/Requests/RegisterRequest.php
// +++ b/app/Http/Requests/RegisterRequest.php
// @@ -15,6 +15,7 @@
//      return [
//          'name' => ['required', 'string', 'max:255'],
// +        'email' => ['required', 'email', new EmailValidationRule()],
//          'password' => ['required', 'confirmed', Rules\Password::defaults()],
//      ];
```

**Implementation Guidelines**:
- Use Claude 3.5 Sonnet (best code generation)
- Provide full file content + line numbers
- Request unified diff format
- Include context lines for apply-ability
- Validate diff syntax before returning

---

### 5. Validation Pipeline

**Purpose**: Ensure generated code is safe and correct

**Stages**:
1. **Syntax Check** - PHP/JS/TS parsing
2. **Pint** - Code formatting
3. **PHPStan** - Static analysis
4. **Pest** - Unit/Feature tests
5. **ESLint/Prettier** - Frontend linting
6. **Security Scan** - SAST tools

**Example**:
```php
$validator = app(ValidationPipeline::class);

$result = $validator->validate($patch);

// Returns:
// ValidationResult {
//   success: true,
//   stage: 'tests',
//   errors: [],
//   warnings: ['Unused import'],
//   metrics: {
//     coverage: 85,
//     complexity: 4
//   }
// }
```

**Failure Handling**:
```php
if (!$result->success) {
    // Ask AI to fix based on error messages
    $fixedPatch = $generator->fix($patch, $result->errors);
    
    // Retry validation (max 3 attempts)
    $result = $validator->validate($fixedPatch);
}
```

---

### 6. CI Orchestration

**Purpose**: Run full test suite in clean environment

**Implementation**:
```php
class CIOrchestrator {
    public function run(string $branch): CIResult {
        // Trigger GitHub Actions
        $workflowRun = $this->github->triggerWorkflow($branch);
        
        // Poll for completion
        $result = $this->pollWorkflowStatus($workflowRun->id);
        
        return new CIResult(
            success: $result->conclusion === 'success',
            logs: $this->fetchLogs($workflowRun->id),
            artifacts: $this->fetchArtifacts($workflowRun->id)
        );
    }
}
```

**Best Practices**:
- Always use separate branch for Meta-Campaign
- Run full test suite + coverage checks
- Include browser tests if UI changed
- Save artifacts (coverage reports, logs)
- Timeout after 30 minutes

---

### 7. PR Creation

**Purpose**: Package changes for human review

**Example**:
```php
$prCreator = app(PRCreator::class);

$pr = $prCreator->create(
    branch: 'meta-campaign/add-email-validation',
    title: '[Meta-Campaign] Add email validation to registration',
    body: $this->generatePRBody($intent, $plan, $validation),
    labels: ['meta-campaign', 'auto-generated', 'needs-review']
);
```

**PR Body Template**:
```markdown
## Meta-Campaign Generated PR

**Intent**: {intent description}

**Risk Level**: {low|medium|high}

**Complexity**: {simple|medium|complex}

---

### Changes Summary

{AI-generated summary}

---

### Files Changed

- `app/Rules/EmailValidationRule.php` - Created new validation rule
- `app/Http/Requests/RegisterRequest.php` - Added email validation
- `tests/Feature/RegistrationTest.php` - Added test coverage

---

### Validation Results

✅ Syntax check passed  
✅ Pint formatting passed  
✅ PHPStan (level 8) passed  
✅ All tests passed (157/157)  
✅ Coverage maintained (85%)

---

### Rollback Strategy

{description of how to rollback}

---

**Meta-Campaign Run ID**: {uuid}  
**Generated At**: {timestamp}  
**Estimated Review Time**: {time}
```

---

## Safety Guardrails

### 1. Restricted Paths

**AI cannot modify these without multi-stakeholder approval**:

```php
config('meta-campaign.restricted_paths', [
    'app/Http/Controllers/Auth/*',
    'app/Actions/Fortify/*',
    'app/Models/User.php',
    'config/auth.php',
    'config/fortify.php',
    'database/migrations/*_create_users_table.php',
    'packages/credential-vault/src/*',
    'packages/meta-campaign/src/PolicyEngine.php',
]);
```

### 2. Policy Engine

```php
interface PolicyInterface {
    public function allows(string $path, string $operation): bool;
    public function requiresApproval(string $path): string; // 'single' | 'tech-lead' | 'multi-stakeholder'
}

class MetaCampaignPolicy implements PolicyInterface {
    public function allows(string $path, string $operation): bool {
        // Check against restricted paths
        foreach (config('meta-campaign.restricted_paths') as $pattern) {
            if (fnmatch($pattern, $path)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function requiresApproval(string $path): string {
        // Docs/tests - single review
        if (str_contains($path, 'docs/') || str_contains($path, 'tests/')) {
            return 'single';
        }
        
        // Auth/security - multi-stakeholder
        if (str_contains($path, 'Auth') || str_contains($path, 'Security')) {
            return 'multi-stakeholder';
        }
        
        // Default - tech lead
        return 'tech-lead';
    }
}
```

### 3. Sandbox Execution

**Docker/Sail Configuration**:
```yaml
# .env.meta-campaign
META_CAMPAIGN_SANDBOX=true
META_CAMPAIGN_TIMEOUT=600       # 10 minutes max
META_CAMPAIGN_MAX_MEMORY=1G
META_CAMPAIGN_NO_NETWORK=true   # No external calls
META_CAMPAIGN_READ_ONLY_FS=true # Read-only except /tmp
```

**Usage**:
```php
class SandboxExecutor {
    public function execute(string $code): ExecutionResult {
        // Spin up isolated Sail container
        $containerId = $this->sail->up('meta-campaign');
        
        try {
            // Apply patch in sandbox
            $this->sail->exec($containerId, "git apply {$patchFile}");
            
            // Run tests in sandbox
            $result = $this->sail->exec($containerId, './vendor/bin/pest');
            
            return new ExecutionResult(
                success: $result->exitCode === 0,
                output: $result->output
            );
        } finally {
            // Always destroy container
            $this->sail->down($containerId);
        }
    }
}
```

---

## Monitoring & Observability

### Audit Trail

**Every Meta-Campaign run must be logged**:

```php
class MetaCampaignRun extends Model {
    protected $fillable = [
        'uuid',
        'intent',
        'plan',
        'located_files',
        'generated_patch',
        'validation_results',
        'ci_results',
        'pr_url',
        'approval_status',
        'deployed_at',
        'rolled_back_at',
        'user_id',
    ];
    
    protected $casts = [
        'intent' => 'array',
        'plan' => 'array',
        'validation_results' => 'array',
        'ci_results' => 'array',
    ];
}
```

### Metrics to Track

- **Success Rate**: % of patches that pass validation
- **Self-Correction Rate**: % of fixes after initial failure
- **Review Time**: Human approval latency
- **Rollback Rate**: % of deployed changes that get rolled back
- **Code Quality**: Static analysis scores over time

---

## Best Practices

### 1. Start Small

Begin with low-risk changes:
- Documentation updates
- Test additions
- Code comments
- Refactoring with no behavior change

### 2. Gradual Complexity Increase

```
Week 1-2: Docs, tests, comments
Week 3-4: Simple features (validation rules, formatting)
Week 5-8: Medium features (new endpoints, services)
Week 9-12: Complex features (multi-file refactoring)
```

### 3. Always Include Tests

**Every patch must include tests**:
- New features → Feature tests
- Bug fixes → Regression tests
- Refactoring → Maintain/improve test coverage

### 4. Human Review Checklist

Before approving Meta-Campaign PR:
- [ ] Read AI-generated summary
- [ ] Review diff for unintended changes
- [ ] Check test coverage didn't decrease
- [ ] Verify CI passed all checks
- [ ] Assess security implications
- [ ] Test locally if high risk
- [ ] Confirm rollback strategy is clear

---

## Error Handling

### Common Failure Modes

1. **Syntax Error**
   - AI generates invalid PHP/JS
   - Caught by validation pipeline
   - AI fixes and retries (max 3x)

2. **Test Failure**
   - Generated code breaks existing tests
   - AI analyzes failure logs
   - Generates fix patch
   - Re-runs validation

3. **Merge Conflict**
   - Base branch changed during generation
   - Automatically rebase and regenerate
   - If conflict persists, abort and notify

4. **CI Timeout**
   - Tests run too long (> 30 min)
   - Abort run
   - Analyze bottleneck
   - Retry with optimized approach

### Rollback Procedures

```php
class RollbackService {
    public function rollback(MetaCampaignRun $run): void {
        if ($run->deployed_at) {
            // Revert PR merge
            $this->github->revertPR($run->pr_url);
            
            // Trigger redeployment
            $this->deployPipeline->deploy('production', 'HEAD~1');
            
            // Mark as rolled back
            $run->update(['rolled_back_at' => now()]);
            
            // Notify stakeholders
            $this->notifications->send('Meta-Campaign rollback', $run);
        }
    }
}
```

---

## Configuration

### Config File: `config/meta-campaign.php`

```php
<?php

return [
    'enabled' => env('META_CAMPAIGN_ENABLED', false),
    
    'ai_provider' => env('META_CAMPAIGN_AI_PROVIDER', 'openai'),
    
    'ai_models' => [
        'classifier' => 'gpt-4-turbo-preview',
        'planner' => 'gpt-4-turbo-preview',
        'generator' => 'claude-3-5-sonnet-20241022',
        'embeddings' => 'text-embedding-3-large',
    ],
    
    'restricted_paths' => [
        'app/Http/Controllers/Auth/*',
        'app/Actions/Fortify/*',
        'config/auth.php',
        'packages/credential-vault/src/*',
    ],
    
    'approval_required' => [
        'trivial' => 'single',      // Docs, tests, comments
        'standard' => 'tech-lead',  // Normal features
        'critical' => 'multi-stakeholder', // Auth, crypto, schema
    ],
    
    'validation' => [
        'timeout' => 600,           // 10 minutes
        'max_retries' => 3,
        'coverage_threshold' => 80,
    ],
    
    'sandbox' => [
        'enabled' => env('META_CAMPAIGN_SANDBOX', true),
        'timeout' => 600,
        'max_memory' => '1G',
        'no_network' => true,
    ],
];
```

---

## Testing Meta-Campaign

### Unit Tests

Test individual components:
```php
test('intent classifier recognizes feature requests', function () {
    $classifier = app(IntentClassifier::class);
    
    $intent = $classifier->classify('Add dark mode to dashboard');
    
    expect($intent->type)->toBe('feature')
        ->and($intent->modules)->toContain('ui')
        ->and($intent->complexity)->toBe('medium');
});
```

### Integration Tests

Test full pipeline:
```php
test('meta-campaign generates valid patch from intent', function () {
    $metaCampaign = app(MetaCampaignService::class);
    
    $result = $metaCampaign->run('Add email validation to registration');
    
    expect($result->success)->toBeTrue()
        ->and($result->prUrl)->toBeString()
        ->and($result->validationPassed)->toBeTrue();
});
```

### E2E Tests

Test with real GitHub integration:
```php
test('meta-campaign creates PR and passes CI', function () {
    // Only run in CI environment
    if (!app()->environment('ci')) {
        $this->markTestSkipped('E2E test only runs in CI');
    }
    
    $metaCampaign = app(MetaCampaignService::class);
    
    $result = $metaCampaign->run('Add copyright year to footer');
    
    // Wait for CI to complete
    $this->waitForCI($result->prUrl);
    
    expect($result->ciPassed)->toBeTrue();
});
```

---

## Future Enhancements

1. **Multi-Patch Coordination** - Handle complex changes requiring multiple PRs
2. **Dependency Analysis** - Detect breaking changes across packages
3. **Performance Regression Detection** - Compare benchmarks before/after
4. **Visual Regression Testing** - Compare screenshots for UI changes
5. **Semantic Versioning** - Auto-determine version bump based on changes
6. **Changelog Generation** - AI-generated release notes
7. **Migration Generation** - Auto-generate DB migrations from model changes

---

## Key Principles

1. **Safety First** - Multiple validation layers, human approval, easy rollback
2. **Audit Everything** - Immutable trail of all Meta-Campaign runs
3. **Start Conservative** - Begin with low-risk changes, expand gradually
4. **Test Coverage Required** - Never decrease test coverage
5. **Clear Communication** - PR descriptions explain intent and changes clearly
6. **Fail Fast** - Abort at first sign of trouble, don't push through errors
7. **Learn & Improve** - Track metrics, iterate on prompts and processes


=== .ai/domain rules ===

# Stash/DeadDrop Domain Guidelines

## Platform Overview

**Stash** is a self-evolving multi-tenant document processing platform powered by the **DeadDrop** mono-repo package ecosystem. It features a revolutionary **Meta-Campaign** system that maintains and upgrades itself using AI.

### Package Structure
- **Package Name**: `3neti/dead-drop`
- **Root Namespace**: `LBHurtado\DeadDrop`
- **Location**: `packages/` directory (Composer workspaces)

---

## Core Concepts

### 1. Multi-Tenancy Architecture

**Model**: Subscriber-based multi-tenancy with hierarchical credential scoping

**Key Entities**:
- **Subscriber** - Top-level tenant (organization/company)
- **User** - Belongs to subscriber, has roles/permissions
- **Campaign** - Document processing workflow owned by subscriber
- **Processor** - Executable step in pipeline

**Scoping Pattern**:
```php
// Always scope queries by tenant
Campaign::forSubscriber($subscriber)->get();

// Use global scopes on models
class Campaign extends Model {
    protected static function booted() {
        static::addGlobalScope(new SubscriberScope);
    }
}
```

---

### 2. Campaign System

**Campaigns** are configurable document processing workflows that:
- Accept document uploads via API or Stashlets
- Execute pipelines with sequential/parallel/branching logic
- Route documents through processors
- Store results and trigger actions

**Campaign Types**:
- **Template Campaign** - Pre-configured workflows (e.g., "Invoice Processing")
- **Custom Campaign** - Subscriber-specific workflows
- **Meta-Campaign** - Special campaign that maintains Stash itself

**Key Attributes**:
- `name`, `description`, `slug`
- `subscriber_id` - Owner
- `pipeline_config` - JSON defining processor graph
- `credentials` - Campaign-level API keys/secrets
- `status` - Active/Paused/Archived

**Pipeline Configuration**:
```json
{
  "processors": [
    {
      "id": "extract_text",
      "type": "LBHurtado\DeadDrop\Processors\OCRProcessor",
      "config": { "language": "en" },
      "next": ["classify_document"]
    },
    {
      "id": "classify_document",
      "type": "LBHurtado\DeadDrop\Processors\ClassifierProcessor",
      "branches": {
        "invoice": ["extract_invoice_fields"],
        "receipt": ["extract_receipt_fields"]
      }
    }
  ]
}
```

---

### 3. Processor Framework

**Processors** are modular, reusable components that perform specific tasks.

**Interface**:
```php
interface ProcessorInterface {
    public function handle(Document $document, array $context): ProcessorResult;
    public function rollback(Document $document): void;
    public function canProcess(Document $document): bool;
}
```

**Built-in Processor Types**:
- **OCRProcessor** - Extract text from images/PDFs
- **ClassifierProcessor** - Categorize documents using AI
- **ExtractorProcessor** - Extract structured data (invoices, forms)
- **ValidatorProcessor** - Validate extracted data
- **EnrichmentProcessor** - Add metadata, lookup data
- **NotificationProcessor** - Send emails, SMS, webhooks
- **StorageProcessor** - Save to S3, databases, APIs

**Processor Lifecycle**:
1. `canProcess()` - Pre-flight check
2. `handle()` - Main processing logic
3. `rollback()` - Undo on failure (if possible)

**Error Handling**:
- Processors throw `ProcessorException` on failure
- Pipeline catches and routes to Dead Letter Queue (DLQ)
- Retry logic with exponential backoff

---

### 4. Credential Vault

**Hierarchical Precedence**:
```
System → Subscriber → Campaign → Processor
```

**Example**:
- System has default OpenAI API key
- Subscriber "ACME Corp" overrides with their own OpenAI key
- Campaign "Invoice OCR" overrides with budget-limited key
- Specific processor can override further

**Implementation**:
```php
class CredentialVault {
    public function resolve(
        string $key,
        ?Campaign $campaign = null,
        ?Subscriber $subscriber = null
    ): ?string {
        // Check processor-level (if in context)
        // Check campaign-level
        if ($campaign && $campaign->credentials->has($key)) {
            return $campaign->credentials->get($key);
        }
        // Check subscriber-level
        if ($subscriber && $subscriber->credentials->has($key)) {
            return $subscriber->credentials->get($key);
        }
        // Check system-level
        return config("credentials.{$key}");
    }
}
```

**Security**:
- Credentials encrypted at rest using Laravel Crypt
- Decrypted in memory only when needed
- Audit log for credential access
- No credentials in logs or error messages

---

### 5. Multi-AI Routing

**Supported Providers**:
- OpenAI (GPT-4, GPT-3.5, GPT-4o)
- Anthropic (Claude 3.5 Sonnet, Claude 3 Opus)
- Google (Gemini Pro)
- AWS Bedrock
- Ollama (local models)
- vLLM (self-hosted)

**Routing Logic**:
```php
interface AIRouterInterface {
    public function route(
        string $task,
        array $context,
        ?string $preferredProvider = null
    ): AIProviderInterface;
}
```

**Task Types**:
- `classification` - Route to fast, cheap model (GPT-3.5)
- `extraction` - Route to accurate model (GPT-4)
- `generation` - Route to creative model (Claude)
- `embedding` - Route to embedding model

**Fallback Strategy**:
- Primary provider fails → try secondary
- Secondary fails → try tertiary
- All fail → queue for retry

---

### 6. Queue Abstraction

**Supported Backends**:
- Redis (default)
- AWS SQS
- RabbitMQ
- Apache Kafka
- Webhooks (push-based)

**Queue Types**:
- **Processing Queue** - Main job queue
- **Priority Queue** - High-priority jobs
- **Dead Letter Queue (DLQ)** - Failed jobs
- **Retry Queue** - Jobs waiting for retry

**Usage Pattern**:
```php
// Dispatch to queue
ProcessDocumentJob::dispatch($document, $campaign)
    ->onQueue('processing')
    ->withPriority(5);

// Handle in processor
class ProcessDocumentJob implements ShouldQueue {
    public function handle(ProcessorInterface $processor) {
        try {
            $processor->handle($this->document, $this->context);
        } catch (ProcessorException $e) {
            $this->sendToDLQ($e);
        }
    }
}
```

---

### 7. Document Model

**Attributes**:
- `uuid` - Unique identifier
- `subscriber_id` - Owner
- `campaign_id` - Campaign that processes this
- `original_filename`
- `mime_type`
- `storage_path` - S3 key or file path
- `status` - Pending/Processing/Completed/Failed
- `metadata` - JSON field for extracted data
- `processing_history` - JSON log of pipeline stages

**Lifecycle**:
1. **Upload** - Document created, stored in S3
2. **Queued** - Job dispatched to queue
3. **Processing** - Pipeline executes processors
4. **Completed** - All processors succeed
5. **Failed** - Processor failed, moved to DLQ

**Storage**:
- Files stored in S3 (or S3-compatible like Minio)
- Metadata stored in PostgreSQL
- Tenant-isolated buckets or prefixes

---

### 8. Stashlets (Embeddable Widgets)

**Vue Components** that embed into external sites:

**DropzoneStashlet**:
```vue
<DropzoneStashlet
  :campaign-id="campaignId"
  :api-url="apiUrl"
  :allowed-types="['pdf', 'png', 'jpg']"
  :max-size="10"
  @upload-success="handleSuccess"
/>
```

**ChecklistStashlet**:
```vue
<ChecklistStashlet
  :document-uuid="documentUuid"
  :show-progress="true"
  @processing-complete="handleComplete"
/>
```

**Distribution**:
- Compiled as standalone JS bundle
- Embeddable via `<script>` tag
- No Vue dependency required (bundled)
- Styled with Tailwind CSS

---

## Meta-Campaign System

### Overview

The **Meta-Campaign** is a revolutionary self-evolution system that uses the same Campaign infrastructure to maintain and upgrade Stash itself.

**Concept**: AI-powered development assistant that:
- Accepts feature requests in natural language
- Plans implementation steps
- Locates relevant code using embeddings
- Generates code patches
- Validates with tests and static analysis
- Creates pull requests
- Monitors deployments

### Meta-Campaign Pipeline

```
1. Intent Classification
   ↓
2. Planning (AI generates task breakdown)
   ↓
3. Code Location (RAG + embeddings)
   ↓
4. Patch Generation (AI writes code)
   ↓
5. Validation (lint, typecheck, tests)
   ↓
6. CI Testing (run full test suite)
   ↓
7. PR Creation (GitHub/GitLab API)
   ↓
8. Review Bundle (human review)
   ↓
9. Staging Deploy (if approved)
   ↓
10. Production Deploy (after monitoring)
```

### Safety Guardrails

**Multi-Layer Protection**:

1. **Restricted Paths** - AI cannot modify:
   - Authentication code
   - Cryptographic functions
   - Database schema migrations (without approval)
   - Credential vault code

2. **Policy Engine**:
   ```php
   interface PolicyInterface {
       public function allows(string $path, string $operation): bool;
   }
   ```

3. **Multi-Role Approval**:
   - Trivial changes (docs, tests) - Single review
   - Logic changes - Tech lead review
   - Security/Auth/Schema - Multi-stakeholder approval

4. **Sandbox Execution**:
   - AI-generated code runs in Docker containers
   - Resource limits (CPU, memory, time)
   - No network access
   - No access to production data

5. **Immutable Audit Trail**:
   - Every Meta-Campaign run logged
   - Git history preserved
   - Rollback procedures

**Break-Glass Procedure**:
- Emergency bypass for critical fixes
- Requires multiple approvals
- Logged and audited
- Auto-notification to all stakeholders

### Code Embeddings & RAG

**Embedding Strategy**:
- Generate embeddings for all PHP/Vue/TS files
- Store in vector database (Meilisearch, Pinecone, or PostgreSQL pgvector)
- Update on every commit

**Hybrid Search**:
```php
class CodeLocator {
    public function find(string $intent): array {
        // 1. Semantic search via embeddings
        $semantic = $this->vectorSearch($intent);
        
        // 2. AST-based search (classes, methods)
        $ast = $this->astSearch($intent);
        
        // 3. Grep for exact symbols
        $grep = $this->grepSearch($intent);
        
        // Merge and rank results
        return $this->merge($semantic, $ast, $grep);
    }
}
```

**Context Window Management**:
- Retrieve relevant code snippets
- Rank by relevance
- Fit into LLM context window (4K-128K tokens)
- Include dependencies and related tests

### Validation Pipeline

**Pre-Commit Checks**:
1. **Laravel Pint** - Code formatting
2. **PHPStan** - Static analysis
3. **Pest** - Unit/Feature tests
4. **ESLint** - JS/TS linting
5. **Prettier** - JS/TS formatting
6. **Security Scan** - SAST tools (e.g., Psalm security plugin)

**CI Checks**:
1. Run full test suite
2. Check code coverage (minimum threshold)
3. Integration tests
4. Browser tests (Dusk/Playwright)
5. Performance benchmarks

**AI Self-Correction**:
- If tests fail, AI analyzes errors
- Generates fix patches
- Re-runs validation
- Max 3 retry attempts

---

## Air-Gapped Deployment

**Use Case**: Government/healthcare environments with strict data isolation

**Architecture**:
- Stash runs fully offline
- Uses local AI models (Ollama, vLLM)
- Local storage (MinIO, NFS)
- No external API calls

**Setup**:
```bash
# .env.airgap
AIR_GAPPED=true
AI_PROVIDER=ollama
AI_MODEL=llama3:70b
QUEUE_DRIVER=redis
STORAGE_DRIVER=local
```

**Data Export/Import**:
- Bundle exports for updates
- Signed and checksummed
- Manual transfer (USB, secure upload)

---

## Development Patterns

### Service Layer

**Always use services for business logic**:
```php
class CampaignService {
    public function createCampaign(
        Subscriber $subscriber,
        CreateCampaignData $data
    ): Campaign {
        // Validation, business rules, persistence
    }
}
```

### Repository Pattern

**Data access through repositories**:
```php
interface CampaignRepository {
    public function findForSubscriber(Subscriber $subscriber): Collection;
    public function findBySlug(string $slug): ?Campaign;
}
```

### Event-Driven Architecture

**Dispatch events at key points**:
```php
// Events
DocumentUploaded::dispatch($document);
ProcessorCompleted::dispatch($document, $processor);
CampaignCompleted::dispatch($campaign, $document);

// Listeners
class SendNotificationOnCompletion {
    public function handle(CampaignCompleted $event) {
        // Send notification
    }
}
```

### Pipeline Pattern

**Use pipelines for sequential operations**:
```php
$result = Pipeline::send($document)
    ->through([
        ExtractText::class,
        ClassifyDocument::class,
        ValidateData::class,
        StoreResults::class,
    ])
    ->thenReturn();
```

---

## Testing Conventions

### Test Organization
- **Unit Tests**: `tests/Unit/DeadDrop/` - Test individual classes
- **Feature Tests**: `tests/Feature/DeadDrop/` - Test HTTP endpoints and workflows
- **Integration Tests**: `tests/Integration/DeadDrop/` - Test processor pipelines

### Factory Usage
```php
// Create test data
$subscriber = Subscriber::factory()->create();
$campaign = Campaign::factory()
    ->for($subscriber)
    ->withProcessors(['ocr', 'classify'])
    ->create();
$document = Document::factory()
    ->for($campaign)
    ->pending()
    ->create();
```

### Mocking AI Providers
```php
// Mock AI responses
$this->mock(AIProviderInterface::class, function ($mock) {
    $mock->shouldReceive('classify')
        ->andReturn('invoice');
});
```

---

## Mono-Repo Package Guidelines

### Package Structure
```
packages/
├── core-skeleton/          # Base models, contracts
├── credential-vault/       # Credential management
├── pipeline-engine/        # Pipeline orchestration
├── processor-framework/    # Processor base classes
├── ai-router/              # Multi-AI routing
├── queue-abstraction/      # Queue adapters
├── meta-campaign/          # Self-evolution system
└── stashlet/               # Embeddable widgets
```

### Package Dependencies
- Packages can depend on other packages
- Use Composer path repositories
- Keep packages loosely coupled

### Package Development Workflow
```bash
# Create new package
mkdir packages/my-package
cd packages/my-package
composer init

# Update root composer.json
composer update

# Run package tests
./vendor/bin/pest packages/my-package/tests
```

---

## Key Principles

1. **Always scope by tenant** - Never leak data between subscribers
2. **Fail gracefully** - Use DLQ, retry logic, error notifications
3. **Audit everything** - Log all significant actions
4. **Encrypt credentials** - Never store secrets in plaintext
5. **Test extensively** - Meta-Campaign code must be bulletproof
6. **Human in the loop** - Critical changes require approval
7. **Immutable history** - Never rewrite Git history
8. **Progressive enhancement** - Start simple, add complexity gradually

---

## Glossary

- **Subscriber** - Tenant/customer of Stash platform
- **Campaign** - Document processing workflow
- **Processor** - Modular task in pipeline
- **Stashlet** - Embeddable Vue widget
- **Meta-Campaign** - Self-evolution AI system
- **DeadDrop** - Mono-repo package namespace
- **DLQ** - Dead Letter Queue for failed jobs
- **RAG** - Retrieval-Augmented Generation (embeddings + search)
- **Air-Gapped** - Fully offline deployment mode


=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.15
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/wayfinder (WAYFINDER) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA) - v2
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== herd rules ===

## Laravel Herd

- The application is served by Laravel Herd and will be available at: https?://[kebab-case-project-dir].test. Use the `get-absolute-url` tool to generate URLs for the user to ensure valid URLs.
- You must not run any commands to make the site available via HTTP(s). It is _always_ available through Laravel Herd.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing / animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use `search-docs` with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use `search-docs` with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use `search-docs` with a query of 'form component resetting' for guidance.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== wayfinder/core rules ===

## Laravel Wayfinder

Wayfinder generates TypeScript functions and types for Laravel controllers and routes which you can import into your client side code. It provides type safety and automatic synchronization between backend routes and frontend code.

### Development Guidelines
- Always use `search-docs` to check wayfinder correct usage before implementing any features.
- Always Prefer named imports for tree-shaking (e.g., `import { show } from '@/actions/...'`)
- Avoid default controller imports (prevents tree-shaking)
- Run `php artisan wayfinder:generate` after route changes if Vite plugin isn't installed

### Feature Overview
- Form Support: Use `.form()` with `--with-form` flag for HTML form attributes — `<form {...store.form()}>` → `action="/posts" method="post"`
- HTTP Methods: Call `.get()`, `.post()`, `.patch()`, `.put()`, `.delete()` for specific methods — `show.head(1)` → `{ url: "/posts/1", method: "head" }`
- Invokable Controllers: Import and invoke directly as functions. For example, `import StorePost from '@/actions/.../StorePostController'; StorePost()`
- Named Routes: Import from `@/routes/` for non-controller routes. For example, `import { show } from '@/routes/post'; show(1)` for route name `post.show`
- Parameter Binding: Detects route keys (e.g., `{post:slug}`) and accepts matching object properties — `show("my-post")` or `show({ slug: "my-post" })`
- Query Merging: Use `mergeQuery` to merge with `window.location.search`, set values to `null` to remove — `show(1, { mergeQuery: { page: 2, sort: null } })`
- Query Parameters: Pass `{ query: {...} }` in options to append params — `show(1, { query: { page: 1 } })` → `"/posts/1?page=1"`
- Route Objects: Functions return `{ url, method }` shaped objects — `show(1)` → `{ url: "/posts/1", method: "get" }`
- URL Extraction: Use `.url()` to get URL string — `show.url(1)` → `"/posts/1"`

### Example Usage

<code-snippet name="Wayfinder Basic Usage" lang="typescript">
    // Import controller methods (tree-shakable)
    import { show, store, update } from '@/actions/App/Http/Controllers/PostController'

    // Get route object with URL and method...
    show(1) // { url: "/posts/1", method: "get" }

    // Get just the URL...
    show.url(1) // "/posts/1"

    // Use specific HTTP methods...
    show.get(1) // { url: "/posts/1", method: "get" }
    show.head(1) // { url: "/posts/1", method: "head" }

    // Import named routes...
    import { show as postShow } from '@/routes/post' // For route name 'post.show'
    postShow(1) // { url: "/posts/1", method: "get" }
</code-snippet>


### Wayfinder + Inertia
If your application uses the `<Form>` component from Inertia, you can use Wayfinder to generate form action and method automatically.
<code-snippet name="Wayfinder Form Component (Vue)" lang="vue">

<Form v-bind="store.form()"><input name="title" /></Form>

</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `php artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== inertia-vue/core rules ===

## Inertia + Vue

- Vue components must have a single root element.
- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="vue">

    import { Link } from '@inertiajs/vue3'
    <Link href="/">Home</Link>

</code-snippet>


=== inertia-vue/v2/forms rules ===

## Inertia + Vue Forms

<code-snippet name="`<Form>` Component Example" lang="vue">

<Form
    action="/users"
    method="post"
    #default="{
        errors,
        hasErrors,
        processing,
        progress,
        wasSuccessful,
        recentlySuccessful,
        setError,
        clearErrors,
        resetAndClearErrors,
        defaults,
        isDirty,
        reset,
        submit,
  }"
>
    <input type="text" name="name" />

    <div v-if="errors.name">
        {{ errors.name }}
    </div>

    <button type="submit" :disabled="processing">
        {{ processing ? 'Creating...' : 'Create User' }}
    </button>

    <div v-if="wasSuccessful">User created successfully!</div>
</Form>

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
