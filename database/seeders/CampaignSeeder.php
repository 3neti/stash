<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Processor;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $processors = Processor::pluck('id', 'slug')->toArray();

        if (empty($processors)) {
            $this->command->warn('No processors found. Run ProcessorSeeder first.');

            return;
        }

        $campaigns = [
            [
                'name' => 'Invoice Processing Pipeline',
                'slug' => 'invoice-processing',
                'description' => 'Automated pipeline for extracting data from invoices',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['tesseract-ocr'] ?? null, 'type' => 'ocr', 'config' => ['language' => 'eng']],
                        ['id' => $processors['document-classifier'] ?? null, 'type' => 'classification', 'config' => ['categories' => ['invoice']]],
                        ['id' => $processors['data-extractor'] ?? null, 'type' => 'extraction', 'config' => ['entity_types' => ['total', 'date', 'vendor']]],
                        ['id' => $processors['schema-validator'] ?? null, 'type' => 'validation', 'config' => ['strict' => true]],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify vendor name', 'required' => true],
                    ['title' => 'Check invoice total', 'required' => true],
                    ['title' => 'Validate payment terms', 'required' => false],
                ],
                'allowed_mime_types' => [
                    'application/pdf',
                    'image/png',
                    'image/jpeg',
                    'image/tiff',
                ],
                'max_file_size_bytes' => 10485760, // 10MB
                'settings' => [
                    'queue' => 'high-priority',
                    'ai_provider' => 'openai',
                ],
                'max_concurrent_jobs' => 5,
                'retention_days' => 90,
                'published_at' => now(),
            ],
            [
                'name' => 'Receipt OCR Workflow',
                'slug' => 'receipt-ocr',
                'description' => 'Extract text and data from receipts',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['openai-vision-ocr'] ?? null, 'type' => 'ocr', 'config' => ['model' => 'gpt-4-vision-preview']],
                        ['id' => $processors['data-extractor'] ?? null, 'type' => 'extraction', 'config' => ['entity_types' => ['merchant', 'total', 'date', 'items']]],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify merchant name', 'required' => true],
                    ['title' => 'Check total amount', 'required' => true],
                ],
                'allowed_mime_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/heic', // iPhone photos
                ],
                'max_file_size_bytes' => 5242880, // 5MB
                'settings' => [
                    'queue' => 'default',
                    'ai_provider' => 'openai',
                ],
                'max_concurrent_jobs' => 10,
                'retention_days' => 60,
                'published_at' => now(),
            ],
            [
                'name' => 'Contract Analysis',
                'slug' => 'contract-analysis',
                'description' => 'Extract key terms and entities from legal contracts',
                'state' => \App\States\Campaign\DraftCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['tesseract-ocr'] ?? null, 'type' => 'ocr', 'config' => ['language' => 'eng', 'dpi' => 600]],
                        ['id' => $processors['data-extractor'] ?? null, 'type' => 'extraction', 'config' => ['entity_types' => ['parties', 'dates', 'terms', 'amounts']]],
                        ['id' => $processors['data-enricher'] ?? null, 'type' => 'enrichment', 'config' => ['enrichment_sources' => ['legal_db']]],
                    ],
                ],
                'allowed_mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                ],
                'max_file_size_bytes' => 20971520, // 20MB (larger for contracts)
                'settings' => [
                    'queue' => 'low-priority',
                    'ai_provider' => 'anthropic',
                ],
                'max_concurrent_jobs' => 3,
                'retention_days' => 365,
            ],
        ];

        foreach ($campaigns as $campaignData) {
            Campaign::updateOrCreate(
                ['slug' => $campaignData['slug']],
                $campaignData
            );
        }

        $this->command->info('Seeded '.count($campaigns).' campaigns');
    }
}
