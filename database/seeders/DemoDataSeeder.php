<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\ProcessorExecution;
use App\Models\UsageEvent;
use App\Models\AuditLog;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campaigns = Campaign::limit(2)->get();
        $processors = Processor::limit(3)->get();

        if ($campaigns->isEmpty()) {
            $this->command->warn('No campaigns found. Run CampaignSeeder first.');
            return;
        }

        if ($processors->isEmpty()) {
            $this->command->warn('No processors found. Run ProcessorSeeder first.');
            return;
        }

        foreach ($campaigns as $campaign) {
            $documents = Document::factory()->count(5)->create([
                'campaign_id' => $campaign->id,
                'user_id' => null,
            ]);

            foreach ($documents as $index => $document) {
                $statusOptions = ['completed', 'processing', 'failed'];
                $status = $statusOptions[$index % 3];

                if ($status === 'completed') {
                    $document->update([
                        'status' => 'completed',
                        'processed_at' => now(),
                        'metadata' => [
                            'extracted' => [
                                'total' => rand(100, 10000),
                                'date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                                'vendor' => fake()->company(),
                            ],
                        ],
                    ]);
                } elseif ($status === 'failed') {
                    $document->update([
                        'status' => 'failed',
                        'error_message' => 'OCR processing timeout',
                        'failed_at' => now(),
                    ]);
                }

                $job = DocumentJob::factory()->create([
                    'campaign_id' => $campaign->id,
                    'document_id' => $document->id,
                    'status' => $status === 'completed' ? 'completed' : ($status === 'failed' ? 'failed' : 'running'),
                ]);

                if ($status === 'completed') {
                    foreach ($processors->take(2) as $processor) {
                        $execution = ProcessorExecution::factory()->completed()->create([
                            'job_id' => $job->id,
                            'processor_id' => $processor->id,
                        ]);

                        UsageEvent::factory()->processorExecution()->create([
                            'campaign_id' => $campaign->id,
                            'document_id' => $document->id,
                            'job_id' => $job->id,
                        ]);
                    }

                    UsageEvent::factory()->aiTask()->create([
                        'campaign_id' => $campaign->id,
                        'document_id' => $document->id,
                        'job_id' => $job->id,
                    ]);
                } elseif ($status === 'failed') {
                    ProcessorExecution::factory()->failed()->create([
                        'job_id' => $job->id,
                        'processor_id' => $processors->first()->id,
                    ]);
                }

                AuditLog::factory()->created()->create([
                    'user_id' => null,
                    'auditable_type' => Document::class,
                    'auditable_id' => $document->id,
                    'new_values' => [
                        'campaign_id' => $campaign->id,
                        'filename' => $document->original_filename,
                    ],
                ]);
            }

            UsageEvent::factory()->upload()->count(5)->create([
                'campaign_id' => $campaign->id,
            ]);

            $this->command->info("Seeded demo data for campaign: {$campaign->name}");
        }

        $this->command->info('Demo data seeding complete');
    }
}
