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
