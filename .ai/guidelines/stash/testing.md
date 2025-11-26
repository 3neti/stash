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
                    'type' => "LBHurtado\\DeadDrop\\Processors\\{$id}Processor",
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
