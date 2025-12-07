<?php

declare(strict_types=1);

use App\Actions\Campaigns\ApplyDefaultTemplates;
use App\Models\Campaign;
use App\Models\Processor;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

beforeEach(function () {
    // Clean up campaigns and processors from previous tests to ensure isolation
    $this->inTenantContext($this->defaultTenant, function () {
        Campaign::query()->forceDelete();
        Processor::query()->forceDelete(); // Clean up processors first
    });

    // Default tenant is already initialized by SetUpsTenantDatabase
    // Register processors needed by templates
    $this->inTenantContext($this->defaultTenant, function () {
        Processor::factory()->create(['slug' => 's3-storage', 'class_name' => 'App\\Processors\\S3StorageProcessor']);
        Processor::factory()->create(['slug' => 'email-notifier', 'class_name' => 'App\\Processors\\EmailNotifierProcessor']);
        Processor::factory()->create(['slug' => 'ekyc-verification', 'class_name' => 'App\\Processors\\EKycVerificationProcessor']);
        Processor::factory()->create(['slug' => 'electronic-signature', 'class_name' => 'App\\Processors\\ElectronicSignatureProcessor']);
        Processor::factory()->create(['slug' => 'tesseract-ocr', 'class_name' => 'App\\Processors\\TesseractOcrProcessor']);
        Processor::factory()->create(['slug' => 'document-classifier', 'class_name' => 'App\\Processors\\DocumentClassifierProcessor']);
        Processor::factory()->create(['slug' => 'schema-validator', 'class_name' => 'App\\Processors\\SchemaValidatorProcessor']);

        // Register processors
        $registry = app(\App\Services\Pipeline\ProcessorRegistry::class);
        $registry->registerFromDatabase();
    });
});

test('applies default templates from config', function () {
    config(['campaigns.default_templates' => ['simple-storage']]);

    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant);

    expect($campaigns)->toHaveCount(1);

    $this->inTenantContext($this->defaultTenant, function () {
        $campaign = Campaign::where('slug', 'simple-storage')->first();

        expect($campaign)->not->toBeNull()
            ->and($campaign->name)->toBe('Simple File Storage')
            ->and($campaign->type)->toBe('template')
            ->and($campaign->pipeline_config['processors'])->toHaveCount(2);
    });
});

test('applies multiple templates', function () {
    $templates = ['simple-storage', 'e-signature-workflow', 'ocr-processing'];

    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, $templates);

    expect($campaigns)->toHaveCount(3);

    $this->inTenantContext($this->defaultTenant, function () {
        expect(Campaign::where('slug', 'simple-storage')->exists())->toBeTrue()
            ->and(Campaign::where('slug', 'e-signature-workflow')->exists())->toBeTrue()
            ->and(Campaign::where('slug', 'ocr-processing')->exists())->toBeTrue();
    });
});

test('applies specific templates overriding config', function () {
    config(['campaigns.default_templates' => ['simple-storage']]);

    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['ocr-processing']);

    expect($campaigns)->toHaveCount(1);

    $this->inTenantContext($this->defaultTenant, function () {
        expect(Campaign::where('slug', 'ocr-processing')->exists())->toBeTrue()
            ->and(Campaign::where('slug', 'simple-storage')->exists())->toBeFalse();
    });
});

test('skips non-existent templates', function () {
    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['non-existent', 'simple-storage']);

    expect($campaigns)->toHaveCount(1)
        ->and($campaigns->first()->slug)->toBe('simple-storage');
});

test('returns empty collection when no templates configured', function () {
    config(['campaigns.default_templates' => []]);

    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant);

    expect($campaigns)->toBeEmpty();
});

test('handles template with JSON format', function () {
    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['simple-storage']);

    expect($campaigns)->toHaveCount(1);

    $this->inTenantContext($this->defaultTenant, function () {
        $campaign = Campaign::where('slug', 'simple-storage')->first();

        expect($campaign)->not->toBeNull()
            ->and($campaign->allowed_mime_types)->toContain('application/pdf');
    });
});

test('handles template with YAML format', function () {
    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['e-signature-workflow']);

    expect($campaigns)->toHaveCount(1);

    $this->inTenantContext($this->defaultTenant, function () {
        $campaign = Campaign::where('slug', 'e-signature-workflow')->first();

        expect($campaign)->not->toBeNull()
            ->and($campaign->retention_days)->toBe(2555);
    });
});

test('creates campaigns in tenant database', function () {
    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['simple-storage']);

    expect($campaigns)->toHaveCount(1);

    $this->inTenantContext($this->defaultTenant, function () use ($campaigns) {
        $campaign = Campaign::find($campaigns->first()->id);

        expect($campaign)->not->toBeNull()
            ->and($campaign->getConnectionName())->toBe('tenant');
    });
});

test('sets campaigns to active state', function () {
    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['simple-storage']);

    $this->inTenantContext($this->defaultTenant, function () {
        $campaign = Campaign::where('slug', 'simple-storage')->first();

        expect($campaign->state)->toBeInstanceOf(\App\States\Campaign\ActiveCampaignState::class);
    });
});

test('applies e-signature template with correct processors', function () {
    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['e-signature-workflow']);

    $this->inTenantContext($this->defaultTenant, function () {
        $campaign = Campaign::where('slug', 'e-signature-workflow')->first();

        expect($campaign)->not->toBeNull()
            ->and($campaign->pipeline_config['processors'])->toHaveCount(4)
            ->and($campaign->pipeline_config['processors'][0]['type'])->toBe('ekycverification')
            ->and($campaign->pipeline_config['processors'][1]['type'])->toBe('electronicsignature');
    });
});

test('applies ocr template with classification step', function () {
    $campaigns = ApplyDefaultTemplates::run($this->defaultTenant, ['ocr-processing']);

    $this->inTenantContext($this->defaultTenant, function () {
        $campaign = Campaign::where('slug', 'ocr-processing')->first();

        expect($campaign)->not->toBeNull()
            ->and($campaign->pipeline_config['processors'])->toHaveCount(5)
            ->and($campaign->pipeline_config['processors'][0]['type'])->toBe('ocr')
            ->and($campaign->pipeline_config['processors'][1]['type'])->toBe('classification');
    });
});
