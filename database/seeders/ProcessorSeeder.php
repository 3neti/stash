<?php

namespace Database\Seeders;

use App\Models\Processor;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class ProcessorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // If running in tenant context, seed processors
        if (TenantContext::isInitialized()) {
            $this->seedProcessors();
        } else {
            // Otherwise, seed for all tenants
            $tenants = Tenant::on('pgsql')->get();
            foreach ($tenants as $tenant) {
                TenantContext::run($tenant, function () {
                    $this->seedProcessors();
                });
            }
        }
    }

    /**
     * Seed processors for current tenant context.
     */
    private function seedProcessors(): void
    {
        $processors = [
            [
                'name' => 'Tesseract OCR',
                'slug' => 'tesseract-ocr',
                'class_name' => 'App\\Processors\\OcrProcessor',
                'category' => 'ocr',
                'description' => 'Extract text from images and scanned documents using Tesseract OCR engine',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'language' => ['type' => 'string', 'default' => 'eng'],
                        'psm' => ['type' => 'integer', 'default' => 3],
                        'dpi' => ['type' => 'integer', 'default' => 300],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'OpenAI Vision OCR',
                'slug' => 'openai-vision-ocr',
                'class_name' => 'App\\Processors\\OpenAIVisionProcessor',
                'category' => 'ocr',
                'description' => 'AI-powered OCR using OpenAI GPT-4 Vision for complex document layouts',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'model' => ['type' => 'string', 'default' => 'gpt-4-vision-preview'],
                        'max_tokens' => ['type' => 'integer', 'default' => 4096],
                        'detail' => ['type' => 'string', 'enum' => ['low', 'high', 'auto'], 'default' => 'auto'],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'Document Classifier',
                'slug' => 'document-classifier',
                'class_name' => 'App\\Processors\\ClassificationProcessor',
                'category' => 'classification',
                'description' => 'Classify documents by type (invoice, receipt, contract, etc.)',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'confidence_threshold' => ['type' => 'number', 'default' => 0.8],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'Data Extractor',
                'slug' => 'data-extractor',
                'class_name' => 'App\\Processors\\ExtractionProcessor',
                'category' => 'extraction',
                'description' => 'Extract structured data entities (dates, amounts, names, etc.)',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'extract_tables' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'Schema Validator',
                'slug' => 'schema-validator',
                'class_name' => 'App\\Processors\\SchemaValidatorProcessor',
                'category' => 'validation',
                'description' => 'Validate extracted data against JSON schema',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'schema' => ['type' => 'object'],
                        'strict' => ['type' => 'boolean', 'default' => false],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'Data Enricher',
                'slug' => 'data-enricher',
                'class_name' => 'App\\Processors\\DataEnricherProcessor',
                'category' => 'enrichment',
                'description' => 'Enrich extracted data with external APIs or databases',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'enrichment_sources' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'cache_results' => ['type' => 'boolean', 'default' => true],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'Email Notifier',
                'slug' => 'email-notifier',
                'class_name' => 'App\\Processors\\EmailNotifierProcessor',
                'category' => 'notification',
                'description' => 'Send email notifications when processing completes',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'recipients' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'template' => ['type' => 'string'],
                        'include_attachments' => ['type' => 'boolean', 'default' => false],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'S3 Storage',
                'slug' => 's3-storage',
                'class_name' => 'App\\Processors\\S3StorageProcessor',
                'category' => 'storage',
                'description' => 'Store processed documents and results in S3',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'bucket' => ['type' => 'string'],
                        'prefix' => ['type' => 'string', 'default' => 'processed/'],
                        'storage_class' => ['type' => 'string', 'default' => 'STANDARD'],
                    ],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
        ];

        foreach ($processors as $processorData) {
            Processor::updateOrCreate(
                ['slug' => $processorData['slug']],
                $processorData
            );
        }

        $this->command->info('Seeded '.count($processors).' system processors');
    }
}
