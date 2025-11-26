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
      "type": "LBHurtado\\DeadDrop\\Processors\\OCRProcessor",
      "config": { "language": "en" },
      "next": ["classify_document"]
    },
    {
      "id": "classify_document",
      "type": "LBHurtado\\DeadDrop\\Processors\\ClassifierProcessor",
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
