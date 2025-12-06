<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\UsesDashboardSetup;

uses(Tests\TestCase::class, UsesDashboardSetup::class);

beforeEach(function () {
    Storage::fake('tenant');
    
    // Setup tenant with unique slug
    [$tenant, $user] = $this->setupDashboardTestTenant();
    $this->tenant = $tenant;
});

it('can store media in kyc_id_cards collection', function () {
    TenantContext::run($this->tenant, function () {
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        $file = UploadedFile::fake()->image('id_card.jpg');
        $contact->addMedia($file)->toMediaCollection('kyc_id_cards');
        
        expect($contact->getMedia('kyc_id_cards'))->toHaveCount(1);
        $media = $contact->getFirstMedia('kyc_id_cards');
        expect($media->file_name)->toBe('id_card.jpg');
    });
});

it('can store media in kyc_selfies collection', function () {
    TenantContext::run($this->tenant, function () {
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        $file = UploadedFile::fake()->image('selfie.jpg');
        $contact->addMedia($file)->toMediaCollection('kyc_selfies');
        
        expect($contact->getMedia('kyc_selfies'))->toHaveCount(1);
        $media = $contact->getFirstMedia('kyc_selfies');
        expect($media->file_name)->toBe('selfie.jpg');
    });
});

it('can store multiple images in kyc_id_cards collection', function () {
    TenantContext::run($this->tenant, function () {
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        $file1 = UploadedFile::fake()->image('id_full.jpg');
        $file2 = UploadedFile::fake()->image('id_cropped.jpg');
        
        $contact->addMedia($file1)->toMediaCollection('kyc_id_cards');
        $contact->addMedia($file2)->toMediaCollection('kyc_id_cards');
        
        expect($contact->getMedia('kyc_id_cards'))->toHaveCount(2);
    });
});

it('kyc_selfies collection is single file', function () {
    TenantContext::run($this->tenant, function () {
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        $file1 = UploadedFile::fake()->image('selfie1.jpg');
        $file2 = UploadedFile::fake()->image('selfie2.jpg');
        
        $contact->addMedia($file1)->toMediaCollection('kyc_selfies');
        $contact->addMedia($file2)->toMediaCollection('kyc_selfies');
        
        // Single file collection replaces previous media
        expect($contact->getMedia('kyc_selfies'))->toHaveCount(1);
        $media = $contact->getFirstMedia('kyc_selfies');
        expect($media->file_name)->toBe('selfie2.jpg');
    });
});

it('uses tenant disk for media storage', function () {
    TenantContext::run($this->tenant, function () {
        $contact = Contact::create([
            'name' => 'Test User',
            'kyc_transaction_id' => 'TEST-123',
        ]);
        
        $file = UploadedFile::fake()->image('id_card.jpg');
        $contact->addMedia($file)->toMediaCollection('kyc_id_cards');
        
        $media = $contact->getFirstMedia('kyc_id_cards');
        expect($media->disk)->toBe('tenant');
    });
});
