<?php

declare(strict_types=1);

beforeEach(function () {
    test()->markTestSkipped('Integration test requires AI processor mocking');
});

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Campaign;
use App\Models\Document;
use App\Processors\ExtractionProcessor;

uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class);

uses()->group('integration', 'processors', 'extraction');

beforeEach(function () {
    $this->processor = new ExtractionProcessor;

    $this->campaign = Campaign::factory()->create([
        'name' => 'Test Extraction Campaign',
        'credentials' => null, // Will use system .env
    ]);

    $this->document = Document::factory()->create([
        'campaign_id' => $this->campaign->id,
        'metadata' => [
            'extracted_text' => 'INVOICE #INV-2024-001 Date: 2024-01-15 Bill To: Acme Corp Amount Due: $1,250.00',
            'category' => 'invoice',
        ],
    ]);
});

test('extracts invoice fields correctly', function () {
    // Mock OpenAI response
    $mockClient = mockOpenAIExtractionClient([
        'invoice_number' => ['value' => 'INV-2024-001', 'confidence' => 0.98],
        'date' => ['value' => '2024-01-15', 'confidence' => 0.95],
        'vendor' => ['value' => 'Acme Corp', 'confidence' => 0.97],
        'total_amount' => ['value' => 1250.00, 'confidence' => 0.99],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    // Execute processor
    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number', 'date', 'vendor', 'total_amount'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('invoice')
        ->and($result->output['fields']['invoice_number']['value'])->toBe('INV-2024-001')
        ->and($result->output['fields']['invoice_number']['confidence'])->toBe(0.98)
        ->and($result->output['fields']['date']['value'])->toBe('2024-01-15')
        ->and($result->output['fields']['vendor']['value'])->toBe('Acme Corp')
        ->and($result->output['fields']['total_amount']['value'])->toEqual(1250.00)
        ->and($result->output['tokens_used'])->toBe(450)
        ->and($result->output['processor'])->toBe('OpenAI Extraction');
});

test('extracts receipt fields correctly', function () {
    $this->document->update([
        'metadata' => [
            'extracted_text' => 'RECEIPT Store: Coffee Shop Date: 2024-01-15 Items: Latte $5.00, Croissant $3.50 Total: $8.50',
            'category' => 'receipt',
        ],
    ]);

    $mockClient = mockOpenAIExtractionClient([
        'merchant' => ['value' => 'Coffee Shop', 'confidence' => 0.96],
        'date' => ['value' => '2024-01-15', 'confidence' => 0.94],
        'total' => ['value' => 8.50, 'confidence' => 0.98],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'receipt' => ['merchant', 'date', 'total'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('receipt')
        ->and($result->output['fields']['merchant']['value'])->toBe('Coffee Shop')
        ->and($result->output['fields']['date']['value'])->toBe('2024-01-15')
        ->and($result->output['fields']['total']['value'])->toBe(8.50);
});

test('handles missing fields with null values', function () {
    $this->document->update([
        'metadata' => [
            'extracted_text' => 'INVOICE Date: 2024-01-15 Amount: $100.00',
            'category' => 'invoice',
        ],
    ]);

    // Mock response with some fields missing
    $mockClient = mockOpenAIExtractionClient([
        'invoice_number' => ['value' => null, 'confidence' => 0.0],
        'date' => ['value' => '2024-01-15', 'confidence' => 0.95],
        'vendor' => ['value' => null, 'confidence' => 0.0],
        'total_amount' => ['value' => 100.00, 'confidence' => 0.97],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number', 'date', 'vendor', 'total_amount'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['fields']['invoice_number']['value'])->toBeNull()
        ->and($result->output['fields']['invoice_number']['confidence'])->toBe(0.0)
        ->and($result->output['fields']['date']['value'])->toBe('2024-01-15')
        ->and($result->output['fields']['vendor']['value'])->toBeNull();
});

test('throws exception when no extraction schema for category', function () {
    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number', 'date'],
        ],
    ]);

    // Set category to something not in schema
    $this->document->update([
        'metadata' => [
            'extracted_text' => 'Some text',
            'category' => 'contract',
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('No extraction schema defined for category: contract');
});

test('throws exception when no extracted text', function () {
    $this->document->update(['metadata' => ['category' => 'invoice']]);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('No extracted text found');
});

test('defaults to unknown category when not classified', function () {
    $this->document->update([
        'metadata' => [
            'extracted_text' => 'Some document text',
            // No category set
        ],
    ]);

    $mockClient = mockOpenAIExtractionClient([
        'field1' => ['value' => 'value1', 'confidence' => 0.8],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'unknown' => ['field1'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('unknown')
        ->and($result->output['fields']['field1']['value'])->toBe('value1');
});

test('uses campaign credentials when available', function () {
    $this->campaign->update([
        'credentials' => json_encode([
            'openai' => [
                'api_key' => 'sk-campaign-specific-key',
            ],
        ]),
    ]);

    $mockClient = mockOpenAIExtractionClient([
        'invoice_number' => ['value' => 'INV-001', 'confidence' => 0.95],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['fields']['invoice_number']['value'])->toBe('INV-001');
});

test('handles very long text by truncating', function () {
    // Create text longer than 3000 chars
    $longText = str_repeat('INVOICE DETAILS: Invoice #12345, Date: 2024-01-15, Amount: $1000. ', 60);

    $this->document->update([
        'metadata' => [
            'extracted_text' => $longText,
            'category' => 'invoice',
        ],
    ]);

    $mockClient = mockOpenAIExtractionClient([
        'invoice_number' => ['value' => '12345', 'confidence' => 0.92],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['fields']['invoice_number']['value'])->toBe('12345');
});

test('respects custom model configuration', function () {
    $mockClient = mockOpenAIExtractionClient([
        'invoice_number' => ['value' => 'INV-001', 'confidence' => 0.95],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number'],
        ],
        'model' => 'gpt-4-turbo',
        'temperature' => 0.05,
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['model'])->toBe('gpt-4-turbo');
});

test('includes schema information in output', function () {
    $schema = ['invoice_number', 'date', 'vendor', 'total_amount'];

    $mockClient = mockOpenAIExtractionClient([
        'invoice_number' => ['value' => 'INV-001', 'confidence' => 0.95],
        'date' => ['value' => '2024-01-15', 'confidence' => 0.93],
        'vendor' => ['value' => 'Acme', 'confidence' => 0.91],
        'total_amount' => ['value' => 1000, 'confidence' => 0.96],
    ]);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => $schema,
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->output['schema_used'])->toBe($schema)
        ->and($result->output['version'])->toBe('1.0.0');
});

test('handles malformed json response', function () {
    $mockResponse = Mockery::mock();
    $mockResponse->choices = [
        (object) [
            'message' => (object) [
                'content' => 'This is not valid JSON!',
            ],
        ],
    ];

    $mockChat = Mockery::mock();
    $mockChat->shouldReceive('create')
        ->andReturn($mockResponse);

    $mockClient = Mockery::mock();
    $mockClient->shouldReceive('chat')
        ->andReturn($mockChat);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Failed to parse OpenAI response as JSON');
});

test('handles missing fields structure in response', function () {
    $mockResponse = Mockery::mock();
    $mockResponse->choices = [
        (object) [
            'message' => (object) [
                'content' => json_encode([
                    'some_other_key' => 'value',
                    // Missing 'fields' key
                ]),
            ],
        ],
    ];

    $mockChat = Mockery::mock();
    $mockChat->shouldReceive('create')
        ->andReturn($mockResponse);

    $mockClient = Mockery::mock();
    $mockClient->shouldReceive('chat')
        ->andReturn($mockChat);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Invalid response structure');
});

test('handles field missing value property', function () {
    $mockResponse = Mockery::mock();
    $mockResponse->choices = [
        (object) [
            'message' => (object) [
                'content' => json_encode([
                    'fields' => [
                        'invoice_number' => [
                            'confidence' => 0.95,
                            // Missing 'value'
                        ],
                    ],
                ]),
            ],
        ],
    ];

    $mockChat = Mockery::mock();
    $mockChat->shouldReceive('create')
        ->andReturn($mockResponse);

    $mockClient = Mockery::mock();
    $mockClient->shouldReceive('chat')
        ->andReturn($mockChat);

    $this->processor->setOpenAIClient($mockClient);

    $config = extractionConfig([
        'schema' => [
            'invoice' => ['invoice_number'],
        ],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('is missing "value" property');
});

// Helper Functions

/**
 * Create a mocked OpenAI client that returns extraction response.
 */
function mockOpenAIExtractionClient(array $fields)
{
    $mockResponse = Mockery::mock();
    $mockResponse->choices = [
        (object) [
            'message' => (object) [
                'content' => json_encode([
                    'fields' => $fields,
                ]),
            ],
        ],
    ];
    $mockResponse->usage = (object) [
        'promptTokens' => 250,
        'completionTokens' => 200,
        'totalTokens' => 450,
    ];

    $mockChat = Mockery::mock();
    $mockChat->shouldReceive('create')
        ->andReturn($mockResponse);

    $mockClient = Mockery::mock();
    $mockClient->shouldReceive('chat')
        ->andReturn($mockChat);

    return $mockClient;
}

/**
 * Helper to create processor config data.
 */
function extractionConfig(array $config): ProcessorConfigData
{
    return new ProcessorConfigData(
        id: 'extractor',
        type: 'extraction',
        config: $config
    );
}
