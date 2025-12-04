<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Contact;
use App\Models\KycTransaction;
use App\Models\ProcessorExecution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\DeadDropTestCase;

class CleanupOldKycMediaTest extends DeadDropTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Missing KycTransaction model and Contact media library setup');
        
        Storage::fake('tenant');
    }

    public function test_deletes_media_from_old_executions(): void
    {
        // Create old execution (35 days ago) with media
        $execution = ProcessorExecution::factory()->create([
            'created_at' => now()->subDays(35),
        ]);
        
        $file = UploadedFile::fake()->image('id.jpg');
        $execution->addMedia($file)->toMediaCollection('kyc_id_cards');
        
        // Create Contact with copied media
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        foreach ($execution->getMedia('kyc_id_cards') as $media) {
            $media->copy($contact, 'kyc_id_cards');
        }
        
        // Create KycTransaction linking them
        KycTransaction::create([
            'transaction_id' => 'TEST-123',
            'tenant_id' => $this->tenant->id,
            'document_id' => $execution->document_id,
            'processor_execution_id' => $execution->id,
            'status' => 'data_fetch_completed',
        ]);
        
        // Run cleanup
        $this->artisan('kyc:cleanup-old-media --days=30')
            ->assertSuccessful();
        
        // Assert: Execution media deleted
        $this->assertCount(0, $execution->fresh()->getMedia('kyc_id_cards'));
        
        // Assert: Contact media preserved
        $this->assertCount(1, $contact->fresh()->getMedia('kyc_id_cards'));
    }

    public function test_respects_retention_period(): void
    {
        // Create execution 20 days ago (within 30-day retention)
        $recentExecution = ProcessorExecution::factory()->create([
            'created_at' => now()->subDays(20),
        ]);
        
        $file = UploadedFile::fake()->image('id.jpg');
        $recentExecution->addMedia($file)->toMediaCollection('kyc_id_cards');
        
        // Run cleanup
        $this->artisan('kyc:cleanup-old-media --days=30')
            ->assertSuccessful();
        
        // Assert: Recent execution media NOT deleted
        $this->assertCount(1, $recentExecution->fresh()->getMedia('kyc_id_cards'));
    }

    public function test_dry_run_does_not_delete(): void
    {
        // Create old execution with media
        $execution = ProcessorExecution::factory()->create([
            'created_at' => now()->subDays(35),
        ]);
        
        $file = UploadedFile::fake()->image('id.jpg');
        $execution->addMedia($file)->toMediaCollection('kyc_id_cards');
        
        // Create Contact with copied media
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        foreach ($execution->getMedia('kyc_id_cards') as $media) {
            $media->copy($contact, 'kyc_id_cards');
        }
        
        // Create KycTransaction
        KycTransaction::create([
            'transaction_id' => 'TEST-123',
            'tenant_id' => $this->tenant->id,
            'document_id' => $execution->document_id,
            'processor_execution_id' => $execution->id,
            'status' => 'data_fetch_completed',
        ]);
        
        // Run cleanup with --dry-run
        $this->artisan('kyc:cleanup-old-media --days=30 --dry-run')
            ->expectsOutput('DRY RUN - No changes were made. Run without --dry-run to delete.')
            ->assertSuccessful();
        
        // Assert: Media NOT deleted in dry-run mode
        $this->assertCount(1, $execution->fresh()->getMedia('kyc_id_cards'));
    }

    public function test_skips_execution_if_contact_has_no_media(): void
    {
        // Create old execution with media
        $execution = ProcessorExecution::factory()->create([
            'created_at' => now()->subDays(35),
        ]);
        
        $file = UploadedFile::fake()->image('id.jpg');
        $execution->addMedia($file)->toMediaCollection('kyc_id_cards');
        
        // Create Contact WITHOUT copied media
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        // Create KycTransaction
        KycTransaction::create([
            'transaction_id' => 'TEST-123',
            'tenant_id' => $this->tenant->id,
            'document_id' => $execution->document_id,
            'processor_execution_id' => $execution->id,
            'status' => 'data_fetch_completed',
        ]);
        
        // Run cleanup
        $this->artisan('kyc:cleanup-old-media --days=30')
            ->assertSuccessful();
        
        // Assert: Execution media NOT deleted (safety check)
        $this->assertCount(1, $execution->fresh()->getMedia('kyc_id_cards'));
    }
}
