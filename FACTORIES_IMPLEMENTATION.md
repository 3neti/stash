# Factory Implementations - Copy to respective files

## DocumentFactory.php

```php
public function definition(): array
{
    return [
        'uuid' => fake()->uuid(),
        'campaign_id' => null, // Set via relationship
        'user_id' => null, // Optional
        'original_filename' => fake()->word() . '.' . fake()->fileExtension(),
        'mime_type' => fake()->mimeType(),
        'size_bytes' => fake()->numberBetween(1024, 10485760), // 1KB to 10MB
        'storage_path' => 'documents/' . fake()->uuid() . '.pdf',
        'storage_disk' => 's3',
        'hash' => hash('sha256', fake()->uuid()),
        'status' => fake()->randomElement(['pending', 'queued', 'processing', 'completed', 'failed']),
        'metadata' => [
            'pages' => fake()->numberBetween(1, 50),
            'language' => 'en',
        ],
        'processing_history' => [],
        'retry_count' => 0,
    ];
}

public function completed(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'completed',
        'processed_at' => fake()->dateTimeBetween('-7 days', 'now'),
    ]);
}

public function failed(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'failed',
        'error_message' => fake()->sentence(),
        'failed_at' => fake()->dateTimeBetween('-7 days', 'now'),
    ]);
}

public function processing(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'processing',
    ]);
}
```

## DocumentJobFactory.php

```php
public function definition(): array
{
    return [
        'uuid' => fake()->uuid(),
        'campaign_id' => null,
        'document_id' => null,
        'pipeline_instance' => [
            'current_step' => 0,
            'total_steps' => 3,
        ],
        'current_processor_index' => 0,
        'status' => fake()->randomElement(['pending', 'queued', 'running', 'completed', 'failed']),
        'queue_name' => 'default',
        'attempts' => 0,
        'max_attempts' => 3,
        'error_log' => [],
    ];
}

public function running(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'running',
        'started_at' => fake()->dateTimeBetween('-1 hour', 'now'),
    ]);
}

public function completed(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'completed',
        'started_at' => fake()->dateTimeBetween('-2 hours', '-1 hour'),
        'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
    ]);
}

public function failed(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'failed',
        'attempts' => 3,
        'error_log' => [
            [
                'timestamp' => now()->toIso8601String(),
                'attempt' => 3,
                'error' => fake()->sentence(),
            ],
        ],
        'failed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
    ]);
}
```

## ProcessorFactory.php

```php
public function definition(): array
{
    return [
        'name' => fake()->words(3, true),
        'slug' => fake()->unique()->slug(),
        'class_name' => 'App\\Processors\\' . fake()->word() . 'Processor',
        'category' => fake()->randomElement(['ocr', 'classification', 'extraction', 'validation', 'enrichment', 'notification', 'storage', 'custom']),
        'description' => fake()->sentence(),
        'config_schema' => [
            'type' => 'object',
            'properties' => [
                'enabled' => ['type' => 'boolean'],
            ],
        ],
        'is_system' => false,
        'is_active' => true,
        'version' => fake()->randomElement(['1.0.0', '1.1.0', '2.0.0']),
        'author' => fake()->name(),
    ];
}

public function system(): static
{
    return $this->state(fn (array $attributes) => [
        'is_system' => true,
    ]);
}

public function inactive(): static
{
    return $this->state(fn (array $attributes) => [
        'is_active' => false,
    ]);
}

public function ocr(): static
{
    return $this->state(fn (array $attributes) => [
        'category' => 'ocr',
        'name' => 'OCR Processor',
        'slug' => 'ocr-processor',
    ]);
}
```

## ProcessorExecutionFactory.php

```php
public function definition(): array
{
    return [
        'job_id' => null,
        'processor_id' => null,
        'input_data' => [
            'document_path' => 'documents/sample.pdf',
        ],
        'output_data' => null,
        'config' => [
            'enabled' => true,
        ],
        'status' => fake()->randomElement(['pending', 'running', 'completed', 'failed', 'skipped']),
        'duration_ms' => null,
        'tokens_used' => 0,
        'cost_credits' => 0,
    ];
}

public function completed(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'completed',
        'output_data' => [
            'text' => fake()->paragraph(),
            'confidence' => fake()->randomFloat(2, 0.8, 1.0),
        ],
        'duration_ms' => fake()->numberBetween(100, 5000),
        'tokens_used' => fake()->numberBetween(50, 2000),
        'cost_credits' => fake()->numberBetween(1, 50),
        'started_at' => fake()->dateTimeBetween('-10 minutes', '-5 minutes'),
        'completed_at' => fake()->dateTimeBetween('-5 minutes', 'now'),
    ]);
}

public function failed(): static
{
    return $this->state(fn (array $attributes) => [
        'status' => 'failed',
        'error_message' => fake()->sentence(),
        'duration_ms' => fake()->numberBetween(100, 1000),
    ]);
}
```

## CredentialFactory.php

```php
public function definition(): array
{
    return [
        'scope_type' => fake()->randomElement(['system', 'subscriber', 'campaign', 'processor']),
        'scope_id' => null,
        'key' => fake()->randomElement(['openai_api_key', 'anthropic_api_key', 'aws_access_key', 'smtp_password']),
        'value' => 'test-credential-' . fake()->uuid(),
        'provider' => fake()->randomElement(['openai', 'anthropic', 'aws', 'smtp']),
        'metadata' => [
            'description' => fake()->sentence(),
        ],
        'expires_at' => null,
        'is_active' => true,
    ];
}

public function system(): static
{
    return $this->state(fn (array $attributes) => [
        'scope_type' => 'system',
        'scope_id' => null,
    ]);
}

public function subscriber(): static
{
    return $this->state(fn (array $attributes) => [
        'scope_type' => 'subscriber',
    ]);
}

public function campaign(): static
{
    return $this->state(fn (array $attributes) => [
        'scope_type' => 'campaign',
    ]);
}

public function processor(): static
{
    return $this->state(fn (array $attributes) => [
        'scope_type' => 'processor',
    ]);
}

public function expired(): static
{
    return $this->state(fn (array $attributes) => [
        'expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
    ]);
}
```

## UsageEventFactory.php

```php
public function definition(): array
{
    return [
        'campaign_id' => null,
        'document_id' => null,
        'job_id' => null,
        'event_type' => fake()->randomElement(['upload', 'storage', 'processor_execution', 'ai_task', 'connector_call', 'agent_tool']),
        'units' => fake()->numberBetween(1, 100),
        'cost_credits' => fake()->numberBetween(1, 50),
        'metadata' => [
            'source' => fake()->word(),
        ],
        'recorded_at' => fake()->dateTimeBetween('-30 days', 'now'),
    ];
}

public function upload(): static
{
    return $this->state(fn (array $attributes) => [
        'event_type' => 'upload',
        'units' => 1,
        'cost_credits' => 1,
    ]);
}

public function aiTask(): static
{
    return $this->state(fn (array $attributes) => [
        'event_type' => 'ai_task',
        'units' => fake()->numberBetween(100, 5000),
        'cost_credits' => fake()->numberBetween(5, 100),
        'metadata' => [
            'model' => 'gpt-4',
            'tokens' => fake()->numberBetween(100, 5000),
        ],
    ]);
}

public function processorExecution(): static
{
    return $this->state(fn (array $attributes) => [
        'event_type' => 'processor_execution',
        'units' => 1,
        'cost_credits' => fake()->numberBetween(1, 10),
    ]);
}
```

## AuditLogFactory.php

```php
public function definition(): array
{
    return [
        'user_id' => null,
        'auditable_type' => fake()->randomElement([
            \App\Models\Campaign::class,
            \App\Models\Document::class,
            \App\Models\Credential::class,
        ]),
        'auditable_id' => fake()->uuid(),
        'event' => fake()->randomElement(['created', 'updated', 'deleted', 'published', 'archived']),
        'old_values' => null,
        'new_values' => [
            'status' => 'active',
        ],
        'ip_address' => fake()->ipv4(),
        'user_agent' => fake()->userAgent(),
        'tags' => [fake()->word()],
    ];
}

public function created(): static
{
    return $this->state(fn (array $attributes) => [
        'event' => 'created',
        'old_values' => null,
    ]);
}

public function updated(): static
{
    return $this->state(fn (array $attributes) => [
        'event' => 'updated',
        'old_values' => [
            'status' => 'draft',
        ],
        'new_values' => [
            'status' => 'active',
        ],
    ]);
}

public function deleted(): static
{
    return $this->state(fn (array $attributes) => [
        'event' => 'deleted',
        'new_values' => null,
    ]);
}
```

---

## Usage Examples

```php
// Create campaigns
Campaign::factory()->count(10)->create();
Campaign::factory()->active()->count(5)->create();
Campaign::factory()->draft()->create();

// Create documents with relationships
Campaign::factory()
    ->has(Document::factory()->count(20))
    ->create();

// Create completed documents
Document::factory()->completed()->count(10)->create();

// Create document job with executions
DocumentJob::factory()
    ->has(ProcessorExecution::factory()->count(3))
    ->create();

// Create system processors
Processor::factory()->system()->ocr()->create();

// Create credentials at different scopes
Credential::factory()->system()->create();
Credential::factory()->campaign()->create();

// Create usage events
UsageEvent::factory()->aiTask()->count(50)->create();

// Create audit logs
AuditLog::factory()->updated()->count(100)->create();
```
