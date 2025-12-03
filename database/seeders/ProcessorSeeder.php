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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                        'pages' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                    'required' => ['text'],
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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                        'model_used' => ['type' => 'string'],
                    ],
                    'required' => ['text'],
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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'category' => ['type' => 'string'],
                        'confidence' => ['type' => 'number'],
                        'categories' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['category', 'confidence'],
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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'entities' => ['type' => 'object'],
                        'tables' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'extraction_confidence' => ['type' => 'number'],
                    ],
                    'required' => ['entities'],
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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'valid' => ['type' => 'boolean'],
                        'errors' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'validated_data' => ['type' => 'object'],
                    ],
                    'required' => ['valid'],
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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'enriched_data' => ['type' => 'object'],
                        'sources_used' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'enrichment_time_ms' => ['type' => 'number'],
                    ],
                    'required' => ['enriched_data'],
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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'sent' => ['type' => 'boolean'],
                        'recipients' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'message_id' => ['type' => 'string'],
                    ],
                    'required' => ['sent'],
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
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'stored' => ['type' => 'boolean'],
                        's3_path' => ['type' => 'string'],
                        'storage_time_ms' => ['type' => 'number'],
                        'file_size_bytes' => ['type' => 'integer'],
                    ],
                    'required' => ['stored', 's3_path'],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'CSV Importer',
                'slug' => 'csv-importer',
                'class_name' => 'App\\Services\\Processors\\PortPHP\\CsvImportProcessor',
                'category' => 'transformation',
                'description' => 'Import and transform CSV files using PortPHP ETL pipeline. No external APIs needed.',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'delimiter' => ['type' => 'string', 'default' => ',', 'description' => 'Column delimiter'],
                        'enclosure' => ['type' => 'string', 'default' => '"', 'description' => 'Field enclosure character'],
                        'escape' => ['type' => 'string', 'default' => '\\', 'description' => 'Escape character'],
                        'has_headers' => ['type' => 'boolean', 'default' => true, 'description' => 'First row contains headers'],
                        'header_row' => ['type' => 'integer', 'default' => 0, 'description' => 'Header row number (0-indexed)'],
                        'date_columns' => ['type' => 'array', 'items' => ['type' => 'string'], 'default' => [], 'description' => 'Columns to convert to DateTime'],
                        'date_format' => ['type' => 'string', 'default' => 'Y-m-d', 'description' => 'Date format for conversion'],
                        'export_json' => ['type' => 'boolean', 'default' => false, 'description' => 'Export imported data as JSON artifact'],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'rows_imported' => ['type' => 'integer'],
                        'rows_failed' => ['type' => 'integer'],
                        'total_rows' => ['type' => 'integer'],
                        'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'has_errors' => ['type' => 'boolean'],
                    ],
                    'required' => ['rows_imported', 'rows_failed', 'total_rows'],
                ],
                'is_system' => true,
                'version' => '1.0.0',
                'author' => 'DeadDrop Team',
            ],
            [
                'name' => 'eKYC Verification',
                'slug' => 'ekyc-verification',
                'class_name' => 'App\\Processors\\EKycVerificationProcessor',
                'category' => 'validation',
                'description' => 'HyperVerge eKYC identity verification with face matching and document OCR',
                'config_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'country' => ['type' => 'string', 'default' => 'PH', 'description' => 'Country code for phone number formatting'],
                        'transaction_id_prefix' => ['type' => 'string', 'default' => 'ekyc', 'description' => 'Prefix for transaction IDs'],
                        'contact_fields' => [
                            'type' => 'object',
                            'properties' => [
                                'mobile' => ['type' => 'string', 'enum' => ['required', 'optional']],
                                'name' => ['type' => 'string', 'enum' => ['required', 'optional']],
                                'email' => ['type' => 'string', 'enum' => ['required', 'optional']],
                            ],
                            'default' => [
                                'mobile' => 'required',
                                'name' => 'required',
                                'email' => 'optional',
                            ],
                        ],
                    ],
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'transaction_id' => ['type' => 'string'],
                        'kyc_link' => ['type' => 'string'],
                        'kyc_status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected']],
                        'contact_id' => ['type' => 'string'],
                        'contact_mobile' => ['type' => 'string'],
                        'contact_name' => ['type' => 'string'],
                        'contact_email' => ['type' => 'string'],
                        'kyc_result' => [
                            'type' => 'object',
                            'properties' => [
                                'application_status' => ['type' => 'string'],
                                'face_match_score' => ['type' => 'number'],
                                'liveness_score' => ['type' => 'number'],
                                'name' => ['type' => 'string'],
                                'birth_date' => ['type' => 'string'],
                                'id_number' => ['type' => 'string'],
                            ],
                        ],
                        'approved_at' => ['type' => 'string'],
                        'rejected_at' => ['type' => 'string'],
                        'rejection_reasons' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => ['transaction_id', 'kyc_link', 'kyc_status'],
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
