<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\ProcessorExecution;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('tenant');
});

test('media copied from execution to contact on approval', function () {
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
});

test('contact media accessible after copy', function () {
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
});

test('both collections copied independently', function () {
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
expect($contact->getMedia('kyc_id_cards'))->toHaveCount(1);
expect($contact->getMedia('kyc_selfies'))->toHaveCount(1);
expect($contact->getFirstMedia('kyc_id_cards')->id)
    ->not->toBe($contact->getFirstMedia('kyc_selfies')->id);
});
