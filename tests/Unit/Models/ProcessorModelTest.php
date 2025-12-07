<?php

declare(strict_types=1);

use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

use App\Models\Processor;
use Illuminate\Support\Str;

beforeEach(function () {
    // Clean up processors from previous tests to ensure isolation
    Processor::query()->forceDelete();
});

it('can create a processor with required fields', function () {
    $processor = Processor::create([
        'name' => 'Test OCR Processor',
        'slug' => 'test-ocr-processor',
        'class_name' => 'App\Processors\TestOcrProcessor',
        'category' => 'ocr',
    ]);

    expect($processor)
        ->name->toBe('Test OCR Processor')
        ->slug->toBe('test-ocr-processor')
        ->class_name->toBe('App\Processors\TestOcrProcessor')
        ->category->toBe('ocr');
});

it('generates ULID automatically on creation', function () {
    $processor = Processor::create([
        'name' => 'Auto ULID Processor',
        'slug' => 'auto-ulid-processor',
        'class_name' => 'App\Processors\AutoUlidProcessor',
        'category' => 'classification',
    ]);

    expect($processor->id)
        ->toBeString()
        ->not->toBeEmpty();
    expect(Str::isUlid($processor->id))->toBeTrue();
});

it('sets default values correctly', function () {
    $processor = Processor::create([
        'name' => 'Default Values Processor',
        'slug' => 'default-values-processor',
        'class_name' => 'App\Processors\DefaultValuesProcessor',
        'category' => 'extraction',
    ]);

    expect($processor)
        ->is_system->toBeFalse()
        ->is_active->toBeTrue()
        ->version->toBe('1.0.0');
});

it('can be created using factory', function () {
    $processor = Processor::factory()->create();

    expect($processor->exists)->toBeTrue();
    expect(Str::isUlid($processor->id))->toBeTrue();
});

it('can be created using factory with custom attributes', function () {
    $processor = Processor::factory()->create([
        'name' => 'Custom Factory Processor',
        'category' => 'validation',
    ]);

    expect($processor)
        ->name->toBe('Custom Factory Processor')
        ->category->toBe('validation');
});

it('can create system processor using factory state', function () {
    $processor = Processor::factory()->system()->create();

    expect($processor->is_system)->toBeTrue();
});

it('can create inactive processor using factory state', function () {
    $processor = Processor::factory()->inactive()->create();

    expect($processor->is_active)->toBeFalse();
});

it('can create OCR processor using factory state', function () {
    $processor = Processor::factory()->ocr()->create();

    expect($processor)
        ->category->toBe('ocr')
        ->name->toBe('OCR Processor')
        ->slug->toBe('ocr-processor');
});

it('casts config_schema to array', function () {
    $schema = [
        'type' => 'object',
        'properties' => [
            'confidence_threshold' => ['type' => 'number'],
            'language' => ['type' => 'string'],
        ],
    ];

    $processor = Processor::factory()->create([
        'config_schema' => $schema,
    ]);

    expect($processor->config_schema)
        ->toBeArray()
        ->toBe($schema);
});

it('casts is_system to boolean', function () {
    $processor = Processor::factory()->create(['is_system' => 1]);

    expect($processor->is_system)->toBeTrue();
    expect($processor->is_system)->toBeBool();
});

it('casts is_active to boolean', function () {
    $processor = Processor::factory()->create(['is_active' => 0]);

    expect($processor->is_active)->toBeFalse();
    expect($processor->is_active)->toBeBool();
});

it('has processorExecutions relationship', function () {
    $processor = Processor::factory()->create();

    expect($processor->processorExecutions())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\HasMany::class);
});

it('can retrieve processorExecutions', function () {
    $processor = Processor::factory()->create();

    // Verify the relationship exists and returns empty collection when no executions
    expect($processor->processorExecutions)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($processor->processorExecutions)->toHaveCount(0);
});

it('scopes active processors', function () {
    Processor::factory()->create(['is_active' => true]);
    Processor::factory()->create(['is_active' => true]);
    Processor::factory()->create(['is_active' => false]);

    $activeProcessors = Processor::active()->get();

    expect($activeProcessors)->toHaveCount(2);
});

it('scopes system processors', function () {
    Processor::factory()->system()->create();
    Processor::factory()->system()->create();
    Processor::factory()->create(['is_system' => false]);

    $systemProcessors = Processor::system()->get();

    expect($systemProcessors)->toHaveCount(2);
});

it('scopes custom processors', function () {
    Processor::factory()->create(['is_system' => false]);
    Processor::factory()->create(['is_system' => false]);
    Processor::factory()->system()->create();

    $customProcessors = Processor::custom()->get();

    expect($customProcessors)->toHaveCount(2);
});

it('scopes processors by category', function () {
    Processor::factory()->create(['category' => 'ocr']);
    Processor::factory()->create(['category' => 'ocr']);
    Processor::factory()->create(['category' => 'classification']);

    $ocrProcessors = Processor::byCategory('ocr')->get();

    expect($ocrProcessors)->toHaveCount(2);
});

it('returns true when processor is active', function () {
    $processor = Processor::factory()->create(['is_active' => true]);

    expect($processor->isActive())->toBeTrue();
});

it('returns false when processor is inactive', function () {
    $processor = Processor::factory()->inactive()->create();

    expect($processor->isActive())->toBeFalse();
});

it('returns true when processor is system', function () {
    $processor = Processor::factory()->system()->create();

    expect($processor->isSystem())->toBeTrue();
});

it('returns false when processor is custom', function () {
    $processor = Processor::factory()->create(['is_system' => false]);

    expect($processor->isSystem())->toBeFalse();
});
