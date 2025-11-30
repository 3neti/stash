<?php

declare(strict_types=1);

use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Models\Campaign;
use App\Models\Document;
use App\Processors\ClassificationProcessor;

uses()->group('integration', 'processors', 'classification');

beforeEach(function () {
    $this->processor = new ClassificationProcessor;

    $this->campaign = Campaign::factory()->create([
        'name' => 'Test Classification Campaign',
        'credentials' => null, // Will use system .env
    ]);

    $this->document = Document::factory()->create([
        'campaign_id' => $this->campaign->id,
        'metadata' => [
            'extracted_text' => 'INVOICE #INV-2024-001 Date: Jan 15, 2024 Bill To: Acme Corp Amount Due: $1,250.00',
        ],
    ]);
});

test('classifies invoice document correctly', function () {
    // Mock OpenAI response
    $mockClient = mockOpenAIClient(
        category: 'invoice',
        confidence: 0.95,
        reasoning: 'Document contains invoice number, billing details, and amount due'
    );

    $this->processor->setOpenAIClient($mockClient);

    // Execute processor
    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'contract', 'other'],
        'model' => 'gpt-4o-mini',
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('invoice')
        ->and($result->output['confidence'])->toBe(0.95)
        ->and($result->output['reasoning'])->toContain('invoice number')
        ->and($result->output['tokens_used'])->toBe(245)
        ->and($result->output['processor'])->toBe('OpenAI Classification');
});

test('classifies receipt document correctly', function () {
    $this->document->update([
        'metadata' => [
            'extracted_text' => 'RECEIPT Store: Coffee Shop Date: 2024-01-15 Items: Latte $5.00, Croissant $3.50 Total: $8.50',
        ],
    ]);

    $mockClient = mockOpenAIClient(
        category: 'receipt',
        confidence: 0.92,
        reasoning: 'Document shows purchase items with prices and total'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'contract', 'other'],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('receipt')
        ->and($result->output['confidence'])->toBe(0.92);
});

test('classifies contract document correctly', function () {
    $this->document->update([
        'metadata' => [
            'extracted_text' => 'SERVICE AGREEMENT This Agreement entered into on January 1, 2024 between Party A and Party B. Terms and Conditions: 1. Services to be provided... Signatures: ___________',
        ],
    ]);

    $mockClient = mockOpenAIClient(
        category: 'contract',
        confidence: 0.98,
        reasoning: 'Document contains agreement language, terms, and signature lines'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'contract', 'other'],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('contract')
        ->and($result->output['confidence'])->toBe(0.98);
});

test('uses campaign credentials when available', function () {
    $this->campaign->update([
        'credentials' => json_encode([
            'openai' => [
                'api_key' => 'sk-campaign-specific-key',
            ],
        ]),
    ]);

    $mockClient = mockOpenAIClient(
        category: 'invoice',
        confidence: 0.90,
        reasoning: 'Test with campaign credentials'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'other'],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('invoice');
});

test('throws exception when no text available', function () {
    $this->document->update(['metadata' => []]);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'other'],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    // Don't mock client, should fail before API call
    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('No extracted text found');
});

test('throws exception when confidence below threshold', function () {
    $mockClient = mockOpenAIClient(
        category: 'other',
        confidence: 0.65,
        reasoning: 'Unclear document type'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'other'],
        'min_confidence' => 0.7,
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('confidence 0.65 is below minimum threshold 0.70');
});

test('throws exception when category not in allowed list', function () {
    $mockClient = mockOpenAIClient(
        category: 'letter',
        confidence: 0.95,
        reasoning: 'This is a letter'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'contract'], // 'letter' not allowed
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('not in allowed categories');
});

test('handles very long text by truncating', function () {
    // Create text longer than 2000 chars
    $longText = str_repeat('INVOICE DETAILS: This is a very long invoice with many line items. ', 50);

    $this->document->update([
        'metadata' => ['extracted_text' => $longText],
    ]);

    $mockClient = mockOpenAIClient(
        category: 'invoice',
        confidence: 0.93,
        reasoning: 'Long invoice document'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'other'],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['category'])->toBe('invoice');
});

test('respects custom model configuration', function () {
    $mockClient = mockOpenAIClient(
        category: 'invoice',
        confidence: 0.91,
        reasoning: 'Custom model test'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'other'],
        'model' => 'gpt-4-turbo',
        'temperature' => 0.5,
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeTrue()
        ->and($result->output['model'])->toBe('gpt-4-turbo');
});

test('includes metadata about available categories', function () {
    $categories = ['invoice', 'receipt', 'contract', 'purchase_order', 'letter', 'other'];

    $mockClient = mockOpenAIClient(
        category: 'invoice',
        confidence: 0.94,
        reasoning: 'Test metadata'
    );

    $this->processor->setOpenAIClient($mockClient);

    $config = processorConfig(['categories' => $categories]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->output['categories_available'])->toBe($categories)
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

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'other'],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Failed to parse OpenAI response as JSON');
});

test('handles missing required fields in response', function () {
    $mockResponse = Mockery::mock();
    $mockResponse->choices = [
        (object) [
            'message' => (object) [
                'content' => json_encode([
                    'category' => 'invoice',
                    // Missing confidence and reasoning
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

    $config = processorConfig([
        'categories' => ['invoice', 'receipt', 'other'],
    ]);

    $context = new ProcessorContextData(
        documentJobId: 'test-job-123',
        processorIndex: 0
    );

    $result = $this->processor->handle($this->document, $config, $context);

    expect($result->success)->toBeFalse()
        ->and($result->error)->toContain('Missing required field');
});

// Helper Functions

/**
 * Create a mocked OpenAI client that returns a classification response.
 */
function mockOpenAIClient(string $category, float $confidence, string $reasoning)
{
    // Create a mock response object
    $mockResponse = Mockery::mock();
    $mockResponse->choices = [
        (object) [
            'index' => 0,
            'message' => (object) [
                'role' => 'assistant',
                'content' => json_encode([
                    'category' => $category,
                    'confidence' => $confidence,
                    'reasoning' => $reasoning,
                ]),
            ],
            'finish_reason' => 'stop',
        ],
    ];
    $mockResponse->usage = (object) [
        'promptTokens' => 150,
        'completionTokens' => 95,
        'totalTokens' => 245,
    ];

    // Create a mock chat instance
    $mockChat = Mockery::mock();
    $mockChat->shouldReceive('create')
        ->andReturn($mockResponse);

    // Create a mock client
    $mockClient = Mockery::mock();
    $mockClient->shouldReceive('chat')
        ->andReturn($mockChat);

    return $mockClient;
}

/**
 * Helper to create processor config data.
 */
function processorConfig(array $config): ProcessorConfigData
{
    return new ProcessorConfigData(
        id: 'classifier',
        type: 'classification',
        config: $config
    );
}
