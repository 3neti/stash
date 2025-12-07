<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Processor;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

beforeEach(function () {
    // Default tenant is already initialized by SetUpsTenantDatabase
    // Create processor in tenant database
    Processor::factory()->create([
        'slug' => 'ocr',
        'name' => 'OCR',
        'class_name' => 'App\\Processors\\OcrProcessor',
    ]);

    // Create temporary test file
    $this->testFile = sys_get_temp_dir().'/test-campaign-'.uniqid().'.json';
});

afterEach(function () {
    if (file_exists($this->testFile)) {
        unlink($this->testFile);
    }
});

test('command imports valid campaign from JSON file', function () {
    $campaignData = [
        'name' => 'CLI Test Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => ['language' => 'eng']],
        ],
    ];

    file_put_contents($this->testFile, json_encode($campaignData));

    $this->artisan('campaign:import', [
        'file' => $this->testFile,
        '--tenant' => $this->defaultTenant->id,
    ])
        ->expectsOutput('✓ Campaign imported successfully!')
        ->assertExitCode(0);

    $campaign = Campaign::where('name', 'CLI Test Campaign')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->name)->toBe('CLI Test Campaign');
});

test('command requires tenant option', function () {
    $campaignData = [
        'name' => 'Test Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    file_put_contents($this->testFile, json_encode($campaignData));

    $this->artisan('campaign:import', [
        'file' => $this->testFile,
    ])
        ->expectsOutput('Tenant ID required. Use --tenant=<id>')
        ->assertExitCode(1);
});

test('command validates file exists', function () {
    $this->artisan('campaign:import', [
        'file' => '/nonexistent/file.json',
        '--tenant' => $this->defaultTenant->id,
    ])
        ->expectsOutputToContain('Parse error')
        ->assertExitCode(1);
});

test('command shows database constraint errors', function () {
    $invalidData = [
        'name' => 'Invalid Campaign',
        'type' => 'invalid-type', // Will fail at database level (CHECK constraint)
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    file_put_contents($this->testFile, json_encode($invalidData));

    $this->artisan('campaign:import', [
        'file' => $this->testFile,
        '--tenant' => $this->defaultTenant->id,
    ])
        ->expectsOutputToContain('Import failed:')
        ->assertExitCode(1);
});

test('command validate-only does not create campaign', function () {
    $campaignData = [
        'name' => 'Validate Only Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    file_put_contents($this->testFile, json_encode($campaignData));

    $this->artisan('campaign:import', [
        'file' => $this->testFile,
        '--tenant' => $this->defaultTenant->id,
        '--validate-only' => true,
    ])
        ->expectsOutput('✓ Validation passed! Campaign definition is valid.')
        ->assertExitCode(0);

    $campaign = Campaign::where('name', 'Validate Only Campaign')->first();

    expect($campaign)->toBeNull();
});

test('command imports from JSON string', function () {
    $campaignData = [
        'name' => 'JSON String Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    $this->artisan('campaign:import', [
        '--json' => json_encode($campaignData),
        '--tenant' => $this->defaultTenant->id,
    ])
        ->expectsOutput('✓ Campaign imported successfully!')
        ->assertExitCode(0);

    $campaign = Campaign::where('name', 'JSON String Campaign')->first();

    expect($campaign)->not->toBeNull()
        ->and($campaign->name)->toBe('JSON String Campaign');
});

test('command rejects invalid JSON string', function () {
    $this->artisan('campaign:import', [
        '--json' => '{invalid json',
        '--tenant' => $this->defaultTenant->id,
    ])
        ->expectsOutputToContain('Parse error:')
        ->assertExitCode(1);
});

test('command requires input source', function () {
    $this->artisan('campaign:import', [
        '--tenant' => $this->defaultTenant->id,
    ])
        ->expectsOutputToContain('No input provided')
        ->assertExitCode(1);
});

test('command prioritizes json option over file', function () {
    // Create a file with different campaign name
    $fileData = [
        'name' => 'File Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];
    file_put_contents($this->testFile, json_encode($fileData));

    // Pass JSON string with different name
    $jsonData = [
        'name' => 'JSON Priority Campaign',
        'type' => 'custom',
        'state' => 'draft',
        'processors' => [
            ['id' => 'ocr', 'type' => 'ocr', 'config' => []],
        ],
    ];

    $this->artisan('campaign:import', [
        'file' => $this->testFile,
        '--json' => json_encode($jsonData),
        '--tenant' => $this->defaultTenant->id,
    ])
        ->assertExitCode(0);

    // Should use JSON, not file
    $campaign = Campaign::where('name', 'JSON Priority Campaign')->first();
    expect($campaign)->not->toBeNull();

    $fileCampaign = Campaign::where('name', 'File Campaign')->first();
    expect($fileCampaign)->toBeNull();
});
