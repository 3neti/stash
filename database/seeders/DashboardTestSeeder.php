<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Document;
use Illuminate\Database\Seeder;

/**
 * Seed test data for Phase 3.1 Subscriber Dashboard.
 *
 * Creates campaigns with various statuses and documents in different states.
 */
class DashboardTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding Dashboard Test Data...');

        // Note: User will be created in central DB by setup command
        // Here we only seed tenant-specific data (campaigns, documents)

        $campaigns = $this->createCampaigns();
        $this->command->info("✓ Created {$campaigns->count()} campaigns");

        $documentCount = $this->createDocuments($campaigns);
        $this->command->info("✓ Created {$documentCount} documents");

        $this->command->info('✅ Dashboard test data seeded successfully!');
    }

    /**
     * Create campaigns with various statuses.
     */
    private function createCampaigns(): \Illuminate\Support\Collection
    {
        $campaigns = collect();

        $campaigns = $campaigns->merge(
            Campaign::factory(3)->create([
                'status' => 'active',
                'type' => 'custom',
                'description' => 'Active campaign for testing',
                'pipeline_config' => [
                    'processors' => [
                        ['type' => 'ocr', 'name' => 'OCR Processor'],
                        ['type' => 'classification', 'name' => 'Document Classifier'],
                        ['type' => 'extraction', 'name' => 'Data Extractor'],
                        ['type' => 'validation', 'name' => 'Validator'],
                    ],
                ],
            ])
        );

        $campaigns = $campaigns->merge(
            Campaign::factory(2)->create([
                'status' => 'paused',
                'type' => 'custom',
                'description' => 'Paused campaign for testing',
            ])
        );

        $campaigns = $campaigns->merge(
            Campaign::factory(1)->create([
                'status' => 'active',
                'type' => 'template',
                'name' => 'Invoice Processing',
                'description' => 'Template for invoice processing workflows',
            ])
        );

        return $campaigns;
    }

    /**
     * Create documents for each campaign.
     */
    private function createDocuments(\Illuminate\Support\Collection $campaigns): int
    {
        $count = 0;

        foreach ($campaigns as $campaign) {
            $docCount = $campaign->status === 'active' ? 5 : 2;

            for ($i = 0; $i < $docCount; $i++) {
                Document::factory()->create([
                    'campaign_id' => $campaign->id,
                    'original_filename' => "test-document-{$count}.pdf",
                ]);

                $count++;
            }
        }

        return $count;
    }
}
