<?php

declare(strict_types=1);

use App\Actions\Campaigns\ImportCampaign;
use App\Data\Campaigns\CampaignImportData;
use App\Models\Campaign;
use App\Models\Processor;
use App\Services\Pipeline\ProcessorRegistry;
use App\States\Campaign\ActiveCampaignState;
use App\States\Campaign\DraftCampaignState;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

beforeEach(function () {
    // Default tenant is already initialized by SetUpsTenantDatabase
    // Create processors in tenant database
    Processor::factory()->create([
        'slug' => 'ocr',
        'name' => 'OCR',
        'class_name' => 'App\\Processors\\OcrProcessor',
    ]);
    Processor::factory()->create([
        'slug' => 'classification',
        'name' => 'Classification',
        'class_name' => 'App\\Processors\\ClassificationProcessor',
    ]);

    // Register processors
    $this->registry = app(ProcessorRegistry::class);
    $this->registry->registerFromDatabase();
});

test('imports valid campaign from array data', function () {
    $data = [
        'name' => 'Test Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => ['language' => 'eng']],
        ],
    ];

    $dto = CampaignImportData::from($data);

    $campaign = ImportCampaign::run($dto, $this->registry);

    expect($campaign)->toBeInstanceOf(Campaign::class)
        ->and($campaign->name)->toBe('Test Campaign')
        ->and($campaign->slug)->toBe('test-campaign')
        ->and($campaign->type)->toBe('custom')
        ->and($campaign->state)->toBeInstanceOf(DraftCampaignState::class)
        ->and($campaign->pipeline_config['processors'])->toHaveCount(1);
});

test('auto-generates slug if missing', function () {
    $data = [
        'name' => 'My Test Campaign',
        'type' => 'template',
        'state' => 'active',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    $dto = CampaignImportData::from($data);

    $campaign = ImportCampaign::run($dto, $this->registry);

    expect($campaign->slug)->toBe('my-test-campaign');
});

test('uses provided slug if given', function () {
    $data = [
        'name' => 'Test Campaign',
        'slug' => 'custom-slug',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    $dto = CampaignImportData::from($data);

    $campaign = ImportCampaign::run($dto, $this->registry);

    expect($campaign->slug)->toBe('custom-slug');
});

test('maps state string to active state class', function () {
    $data = [
        'name' => 'Active Campaign',
        'type' => 'custom',
        'state' => 'active',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    $dto = CampaignImportData::from($data);

    $campaign = ImportCampaign::run($dto, $this->registry);

    expect($campaign->state)->toBeInstanceOf(ActiveCampaignState::class);
});

test('validates processor types exist in registry', function () {
    $data = [
        'name' => 'Test Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'invalid', 'type' => 'nonexistent-processor', 'config' => []],
        ],
    ];

    $dto = CampaignImportData::from($data);

    ImportCampaign::run($dto, $this->registry);
})->throws(\InvalidArgumentException::class, 'Unknown processor type');

test('validates step IDs are unique', function () {
    $data = [
        'name' => 'Test Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'step1', 'type' => 'ocr', 'config' => []],
            ['id' => 'step1', 'type' => 'classification', 'config' => []],
        ],
    ];

    $dto = CampaignImportData::from($data);

    ImportCampaign::run($dto, $this->registry);
})->throws(\InvalidArgumentException::class, 'Duplicate step ID');

test('rejects missing required field name', function () {
    $data = [
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    CampaignImportData::from($data);
})->throws(\Spatie\LaravelData\Exceptions\CannotCreateData::class, 'name');

test('rejects empty processors array', function () {
    $data = [
        'name' => 'Test Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [],
    ];

    // Spatie Data DTOs don't validate Min(1) at creation time
    // Validation happens when used with Laravel validation or explicitly validated
    $dto = CampaignImportData::from($data);
    
    // ImportCampaign should reject it though
    ImportCampaign::run($dto, $this->registry);
})->throws(\InvalidArgumentException::class);

test('accepts invalid state value but defaults to draft', function () {
    $data = [
        'name' => 'Test Campaign',
        'type' => 'custom',
        'state' => 'invalid-state',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    // Spatie Data DTOs don't validate In() at creation time
    $dto = CampaignImportData::from($data);
    
    // ImportCampaign maps unknown states to draft (default in match expression)
    $campaign = ImportCampaign::run($dto, $this->registry);
    
    expect($campaign->state)->toBeInstanceOf(\App\States\Campaign\DraftCampaignState::class);
});

test('rejects invalid type value at database level', function () {
    $data = [
        'name' => 'Test Campaign',
        'type' => 'invalid-type',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    // Spatie Data DTOs don't validate In() at creation time
    $dto = CampaignImportData::from($data);
    
    // PostgreSQL CHECK constraint rejects invalid type values
    ImportCampaign::run($dto, $this->registry);
})->throws(\Illuminate\Database\QueryException::class, 'campaigns_type_check');

test('imports campaign with multiple processors', function () {
    $data = [
        'name' => 'Multi Processor Campaign',
        'type' => 'template',
        'state' => 'active',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => ['language' => 'eng']],
            ['id' => 'classify', 'type' => 'classification', 'config' => ['categories' => ['invoice', 'receipt']]],
        ],
    ];

    $dto = CampaignImportData::from($data);

    $campaign = ImportCampaign::run($dto, $this->registry);

    expect($campaign->pipeline_config['processors'])->toHaveCount(2)
        ->and($campaign->pipeline_config['processors'][0]['id'])->toBe('ocr')
        ->and($campaign->pipeline_config['processors'][1]['id'])->toBe('classify');
});

test('imports campaign with optional fields', function () {
    $data = [
        'name' => 'Full Campaign',
        'description' => 'Test description',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
        'settings' => ['locale' => 'en', 'queue' => 'default'],
        'allowed_mime_types' => ['application/pdf', 'image/png'],
        'max_file_size_bytes' => 5242880,
        'max_concurrent_jobs' => 5,
        'retention_days' => 30,
        'checklist_template' => [['title' => 'Verify data', 'required' => true]],
    ];

    $dto = CampaignImportData::from($data);

    $campaign = ImportCampaign::run($dto, $this->registry);

    expect($campaign->description)->toBe('Test description')
        ->and($campaign->settings)->toBe(['locale' => 'en', 'queue' => 'default'])
        ->and($campaign->allowed_mime_types)->toBe(['application/pdf', 'image/png'])
        ->and($campaign->max_file_size_bytes)->toBe(5242880)
        ->and($campaign->max_concurrent_jobs)->toBe(5)
        ->and($campaign->retention_days)->toBe(30)
        ->and($campaign->checklist_template)->toHaveCount(1);
});

test('creates campaign in tenant database', function () {
    $data = [
        'name' => 'Tenant Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    $dto = CampaignImportData::from($data);

    $campaign = ImportCampaign::run($dto, $this->registry);

    // Verify campaign exists in tenant database
    $found = Campaign::find($campaign->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($campaign->id);
});
