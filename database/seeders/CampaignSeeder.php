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
        $processors = Processor::pluck('slug', 'id')->toArray();

        if (empty($processors)) {
            $this->command->warn('No processors found. Run ProcessorSeeder first.');

            return;
        }

        $processorIds = array_keys($processors);

        $campaigns = [
            [
                'name' => 'Invoice Processing Pipeline',
                'slug' => 'invoice-processing',
                'description' => 'Automated pipeline for extracting data from invoices',
                'status' => 'active',
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processorIds[0] ?? null, 'config' => ['language' => 'eng']],
                        ['id' => $processorIds[2] ?? null, 'config' => ['categories' => ['invoice']]],
                        ['id' => $processorIds[3] ?? null, 'config' => ['entity_types' => ['total', 'date', 'vendor']]],
                        ['id' => $processorIds[4] ?? null, 'config' => ['strict' => true]],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify vendor name', 'required' => true],
                    ['title' => 'Check invoice total', 'required' => true],
                    ['title' => 'Validate payment terms', 'required' => false],
                ],
                'settings' => [
                    'queue' => 'high-priority',
                    'ai_provider' => 'openai',
                    'max_file_size' => 10485760,
                ],
                'max_concurrent_jobs' => 5,
                'retention_days' => 90,
                'published_at' => now(),
            ],
            [
                'name' => 'Receipt OCR Workflow',
                'slug' => 'receipt-ocr',
                'description' => 'Extract text and data from receipts',
                'status' => 'active',
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processorIds[1] ?? null, 'config' => ['model' => 'gpt-4-vision-preview']],
                        ['id' => $processorIds[3] ?? null, 'config' => ['entity_types' => ['merchant', 'total', 'date', 'items']]],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify merchant name', 'required' => true],
                    ['title' => 'Check total amount', 'required' => true],
                ],
                'settings' => [
                    'queue' => 'default',
                    'ai_provider' => 'openai',
                    'max_file_size' => 5242880,
                ],
                'max_concurrent_jobs' => 10,
                'retention_days' => 60,
                'published_at' => now(),
            ],
            [
                'name' => 'Contract Analysis',
                'slug' => 'contract-analysis',
                'description' => 'Extract key terms and entities from legal contracts',
                'status' => 'draft',
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processorIds[0] ?? null, 'config' => ['language' => 'eng', 'dpi' => 600]],
                        ['id' => $processorIds[3] ?? null, 'config' => ['entity_types' => ['parties', 'dates', 'terms', 'amounts']]],
                        ['id' => $processorIds[5] ?? null, 'config' => ['enrichment_sources' => ['legal_db']]],
                    ],
                ],
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
