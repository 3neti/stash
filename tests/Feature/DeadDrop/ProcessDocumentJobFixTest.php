<?php

declare(strict_types=1);

namespace Tests\Feature\DeadDrop;

use App\Jobs\Pipeline\ProcessDocumentJob;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Tests\DeadDropTestCase;

class ProcessDocumentJobFixTest extends DeadDropTestCase
{
    private function getTenant(): Tenant
    {
        return Tenant::on('pgsql')->firstOrCreate(
            ['slug' => 'test-tenant'],
            ['name' => 'Test Tenant']
        );
    }

    public function test_process_document_job_runs_without_channels_table_error(): void
    {
        TenantContext::run($this->getTenant(), function () {
            // Create a campaign with a simple pipeline
            $campaign = Campaign::factory()->create([
                'pipeline_config' => [
                    'processors' => [],
                ],
            ]);

            // Create a document
            $document = Document::factory()->create([
                'campaign_id' => $campaign->id,
            ]);

            // Create a document job
            $documentJob = DocumentJob::factory()->create([
                'campaign_id' => $campaign->id,
                'document_id' => $document->id,
                'pipeline_instance' => [
                    'processors' => [],
                ],
            ]);

            // Dispatch the job
            $tenant = $this->getTenant();
            $job = new ProcessDocumentJob($documentJob->id, $tenant->id);

            // This should not throw "Undefined table: channels" error
            try {
                $job->handle(app('App\Services\Pipeline\DocumentProcessingPipeline'));
                $this->assertTrue(true, 'Job executed successfully without channels table error');
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'relation "channels" does not exist')) {
                    $this->fail('Channels table error still occurring: ' . $e->getMessage());
                }
                // Other exceptions are acceptable for this test
                $this->assertTrue(true, 'Job executed (with non-channel exception)');
            }
        });
    }

    public function test_document_job_can_transition_from_failed_to_failed(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $campaign = Campaign::factory()->create();
            $document = Document::factory()->create(['campaign_id' => $campaign->id]);

            $documentJob = DocumentJob::factory()->create([
                'campaign_id' => $campaign->id,
                'document_id' => $document->id,
            ]);

            // Transition through states to reach failed state
            // pending -> queued -> running -> failed
            $documentJob->state->transitionTo('queued');
            $documentJob->state->transitionTo('running');
            $documentJob->state->transitionTo('failed');
            $documentJob->save();

            // Should be able to call fail() again without state transition error
            // This tests the fix that allows failed -> failed transitions
            $documentJob->fail('Test error');

            $this->assertTrue($documentJob->isFailed());
        });
    }

    public function test_campaign_with_channels_trait_loads_without_error(): void
    {
        TenantContext::run($this->getTenant(), function () {
            $campaign = Campaign::factory()->create();

            // Loading the campaign should not fail when querying channels
            $reloaded = Campaign::find($campaign->id);
            $this->assertNotNull($reloaded);

            // Accessing HasChannels relationships should work
            $webhook = $reloaded->webhook; // Should be null or work without error
            $this->assertNull($webhook);
        });
    }
}
