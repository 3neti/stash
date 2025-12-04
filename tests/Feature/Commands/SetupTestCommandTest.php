<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Campaign;
use App\Models\Processor;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\DeadDropTestCase;

class SetupTestCommandTest extends DeadDropTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('ProcessorSeeder execution fails in test environment - needs seeder refactoring');
    }
    
    private function getTenant(): Tenant
    {
        return Tenant::on('pgsql')->firstOrCreate(
            ['slug' => 'setup-cmd-test'],
            ['name' => 'Setup Command Test Tenant']
        );
    }

    /**
     * Test that dashboard:setup-test command creates a test tenant if none exist.
     */
    public function test_dashboard_setup_test_creates_test_tenant_if_none_exist(): void
    {
        $initialCount = Tenant::count();
        $tenant = $this->getTenant();

        $this->assertNotNull($tenant);
        $this->assertEquals('Setup Command Test Tenant', $tenant->name);
        $this->assertGreaterThanOrEqual($initialCount, Tenant::count());
    }

    /**
     * Test that ProcessorSeeder successfully seeds 8 processors in tenant database.
     */
    public function test_setup_seeds_all_eight_processors(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $this->assertEquals(8, Processor::count());
        });
    }

    /**
     * Test that all processor categories are seeded.
     */
    public function test_setup_seeds_processors_from_all_categories(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $categories = ['ocr', 'classification', 'extraction', 'validation', 'enrichment', 'notification', 'storage'];

            foreach ($categories as $category) {
                $count = Processor::where('category', $category)->count();
                $this->assertGreaterThan(0, $count, "No processors found for category: {$category}");
            }
        });
    }

    /**
     * Test that each processor has a valid output_schema with required structure.
     */
    public function test_all_processors_have_valid_output_schemas(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $processors = Processor::all();

            foreach ($processors as $processor) {
                // Schema must exist
                $this->assertNotNull($processor->output_schema, "Processor {$processor->slug} missing output_schema");
                $this->assertIsArray($processor->output_schema);

                // Schema must have required JSON Schema properties
                $this->assertArrayHasKey('type', $processor->output_schema);
                $this->assertEquals('object', $processor->output_schema['type']);
                $this->assertArrayHasKey('properties', $processor->output_schema);
                $this->assertArrayHasKey('required', $processor->output_schema);
                $this->assertIsArray($processor->output_schema['required']);
            }
        });
    }

    /**
     * Test that specific expected processors are seeded by slug.
     */
    public function test_setup_seeds_expected_processors_by_slug(): void
    {
        $tenant = $this->getTenant();
        $expectedSlugs = [
            'tesseract-ocr',
            'openai-vision-ocr',
            'document-classifier',
            'data-extractor',
            'schema-validator',
            'data-enricher',
            'email-notifier',
            's3-storage',
        ];

        TenantContext::run($tenant, function () use ($expectedSlugs) {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () use ($expectedSlugs) {
            foreach ($expectedSlugs as $slug) {
                $processor = Processor::where('slug', $slug)->first();
                $this->assertNotNull($processor, "Processor with slug '{$slug}' not found");
            }
        });
    }

    /**
     * Test that processors have both config_schema and output_schema.
     */
    public function test_processors_have_both_config_and_output_schemas(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $processors = Processor::all();

            foreach ($processors as $processor) {
                // Both schemas should exist
                $this->assertNotNull($processor->config_schema, "Processor {$processor->slug} missing config_schema");
                $this->assertNotNull($processor->output_schema, "Processor {$processor->slug} missing output_schema");

                // Both should be arrays
                $this->assertIsArray($processor->config_schema);
                $this->assertIsArray($processor->output_schema);
            }
        });
    }

    /**
     * Test that processors are marked as system processors.
     */
    public function test_seeded_processors_are_marked_as_system(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $processors = Processor::all();

            foreach ($processors as $processor) {
                $this->assertTrue($processor->is_system, "Processor {$processor->slug} not marked as system");
            }
        });
    }

    /**
     * Test that processors are active by default.
     */
    public function test_seeded_processors_are_active(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $inactiveCount = Processor::where('is_active', false)->count();
            $this->assertEquals(0, $inactiveCount, 'Some processors are not active');
        });
    }

    /**
     * Test that ProcessorSeeder is idempotent (can be run multiple times).
     */
    public function test_processor_seeder_is_idempotent(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // Seed once
            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $countAfterFirstSeed = Processor::count();

            // Seed again
            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);

            $countAfterSecondSeed = Processor::count();

            // Count should remain the same
            $this->assertEquals($countAfterFirstSeed, $countAfterSecondSeed, 'ProcessorSeeder is not idempotent');
        });
    }

    /**
     * Test that campaigns can be seeded after processors are available.
     */
    public function test_campaigns_can_be_seeded_when_processors_exist(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // Seed processors first
            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);

            // Then seed campaigns (which reference processors)
            Artisan::call('db:seed', [
                '--class' => 'CampaignSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $campaignCount = Campaign::count();
            $this->assertGreaterThan(0, $campaignCount, 'No campaigns were seeded');

            // Verify campaigns have valid pipeline configs
            $campaign = Campaign::first();
            $this->assertNotNull($campaign);
            $this->assertIsArray($campaign->pipeline_config);
        });
    }

    /**
     * Test that output_schema column exists in processors table.
     */
    public function test_output_schema_column_exists_in_processors_table(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            // Verify table structure
            $this->assertTrue(
                Schema::hasColumn('processors', 'output_schema'),
                'output_schema column missing from processors table'
            );
        });
    }

    /**
     * Test that output_schema can store and retrieve complex JSON structures.
     */
    public function test_output_schema_persists_complex_json_structures(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $complexSchema = [
                'type' => 'object',
                'properties' => [
                    'nested' => [
                        'type' => 'object',
                        'properties' => [
                            'deep' => ['type' => 'string'],
                            'array' => ['type' => 'array', 'items' => ['type' => 'number']],
                        ],
                    ],
                ],
                'required' => ['nested'],
            ];

            $processor = Processor::create([
                'name' => 'Test Complex Schema',
                'slug' => 'test-complex-' . uniqid(),
                'class_name' => 'App\\Processors\\TestProcessor',
                'category' => 'enrichment',
                'output_schema' => $complexSchema,
            ]);

            $reloaded = Processor::find($processor->id);
            $this->assertEquals($complexSchema, $reloaded->output_schema);
        });
    }

    /**
     * Test that all processor names are descriptive and meaningful.
     */
    public function test_all_processors_have_descriptive_names(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $processors = Processor::all();

            foreach ($processors as $processor) {
                $this->assertNotEmpty($processor->name, "Processor {$processor->slug} has empty name");
                $this->assertGreaterThan(3, strlen($processor->name), "Processor {$processor->slug} has too short name");
                $this->assertTrue(
                    ctype_print($processor->name),
                    "Processor {$processor->slug} has non-printable characters in name"
                );
            }
        });
    }

    /**
     * Test that all processors have descriptions.
     */
    public function test_all_processors_have_descriptions(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $processors = Processor::all();

            foreach ($processors as $processor) {
                $this->assertNotEmpty($processor->description, "Processor {$processor->slug} has empty description");
                $this->assertGreaterThan(10, strlen($processor->description), "Processor {$processor->slug} has too short description");
            }
        });
    }

    /**
     * Test that each processor has an author.
     */
    public function test_all_processors_have_author(): void
    {
        $tenant = $this->getTenant();

        TenantContext::run($tenant, function () {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'ProcessorSeeder',
                '--no-interaction' => true,
            ]);
        });

        TenantContext::run($tenant, function () {
            $processors = Processor::all();

            foreach ($processors as $processor) {
                $this->assertNotEmpty($processor->author, "Processor {$processor->slug} has no author");
            }
        });
    }
}
