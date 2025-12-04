<?php

declare(strict_types=1);

namespace Tests\Feature\DeadDrop;

use App\Models\Contact;
use App\Models\ProcessorExecution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\DeadDropTestCase;

class CopyKycMediaToContactTest extends DeadDropTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('tenant');
    }

    public function test_media_copied_from_execution_to_contact_on_approval(): void
    {
        // Create ProcessorExecution with KYC images
        $execution = ProcessorExecution::factory()->create();
        
        $idCard1 = UploadedFile::fake()->image('id_full.jpg');
        $idCard2 = UploadedFile::fake()->image('id_cropped.jpg');
        $selfie = UploadedFile::fake()->image('selfie.jpg');
        
        $execution->addMedia($idCard1)->toMediaCollection('kyc_id_cards');
        $execution->addMedia($idCard2)->toMediaCollection('kyc_id_cards');
        $execution->addMedia($selfie)->toMediaCollection('kyc_selfies');
        
        $this->assertCount(2, $execution->getMedia('kyc_id_cards'));
        $this->assertCount(1, $execution->getMedia('kyc_selfies'));
        
        // Create Contact
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-'.time(),
        ]);
        
        // Copy media from execution to contact
        foreach ($execution->getMedia('kyc_id_cards') as $media) {
            $media->copy($contact, 'kyc_id_cards');
        }
        foreach ($execution->getMedia('kyc_selfies') as $media) {
            $media->copy($contact, 'kyc_selfies');
        }
        
        // Assert: Contact has all media
        $this->assertCount(2, $contact->getMedia('kyc_id_cards'));
        $this->assertCount(1, $contact->getMedia('kyc_selfies'));
        
        // Assert: Original media on execution still exists
        $this->assertCount(2, $execution->fresh()->getMedia('kyc_id_cards'));
        $this->assertCount(1, $execution->fresh()->getMedia('kyc_selfies'));
    }

    public function test_contact_media_accessible_after_copy(): void
    {
        $execution = ProcessorExecution::factory()->create();
        
        $idCard = UploadedFile::fake()->image('id_card.jpg');
        $execution->addMedia($idCard)->toMediaCollection('kyc_id_cards');
        
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-'.time(),
        ]);
        
        // Copy
        foreach ($execution->getMedia('kyc_id_cards') as $media) {
            $media->copy($contact, 'kyc_id_cards');
        }
        
        // Assert: Contact media is accessible
        $contactMedia = $contact->getFirstMedia('kyc_id_cards');
        $this->assertNotNull($contactMedia);
        $this->assertEquals('id_card.jpg', $contactMedia->file_name);
    }

    public function test_both_collections_copied_independently(): void
    {
        $execution = ProcessorExecution::factory()->create();
        
        $idCard = UploadedFile::fake()->image('id.jpg');
        $selfie = UploadedFile::fake()->image('selfie.jpg');
        
        $execution->addMedia($idCard)->toMediaCollection('kyc_id_cards');
        $execution->addMedia($selfie)->toMediaCollection('kyc_selfies');
        
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-'.time(),
        ]);
        
        // Copy both collections
        foreach ($execution->getMedia('kyc_id_cards') as $media) {
            $media->copy($contact, 'kyc_id_cards');
        }
        foreach ($execution->getMedia('kyc_selfies') as $media) {
            $media->copy($contact, 'kyc_selfies');
        }
        
        // Assert: Both collections exist independently
        $this->assertCount(1, $contact->getMedia('kyc_id_cards'));
        $this->assertCount(1, $contact->getMedia('kyc_selfies'));
        $this->assertNotEquals(
            $contact->getFirstMedia('kyc_id_cards')->id,
            $contact->getFirstMedia('kyc_selfies')->id
        );
    }
}
