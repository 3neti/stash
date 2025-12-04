<?php

declare(strict_types=1);

use App\Models\Contact;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function () {
    Storage::fake('tenant');
    
    // Initialize tenant context
    $this->tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant',
        'database' => 'tenant_test',
    ]);
    
    app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($this->tenant);
});

it('can store media in kyc_id_cards collection', function () {
    $contact = Contact::create([
        'name' => 'Test User',
        'kyc_transaction_id' => 'TEST-123',
    ]);
    
    $file = UploadedFile::fake()->image('id_card.jpg');
    $contact->addMedia($file)->toMediaCollection('kyc_id_cards');
    
    expect($contact->getMedia('kyc_id_cards'))->toHaveCount(1);
    expect($contact->getFirstMedia('kyc_id_cards')->file_name)->toBe('id_card.jpg');
});

it('can store media in kyc_selfies collection', function () {
    $contact = Contact::create([
        'name' => 'Test User',
        'kyc_transaction_id' => 'TEST-123',
    ]);
    
    $file = UploadedFile::fake()->image('selfie.jpg');
    $contact->addMedia($file)->toMediaCollection('kyc_selfies');
    
    expect($contact->getMedia('kyc_selfies'))->toHaveCount(1);
    expect($contact->getFirstMedia('kyc_selfies')->file_name)->toBe('selfie.jpg');
});

it('can store multiple images in kyc_id_cards collection', function () {
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

it('kyc_selfies collection is single file', function () {
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
    expect($contact->getFirstMedia('kyc_selfies')->file_name)->toBe('selfie2.jpg');
});

it('uses tenant disk for media storage', function () {
    $contact = Contact::create([
        'name' => 'Test User',
        'kyc_transaction_id' => 'TEST-123',
    ]);
    
    $file = UploadedFile::fake()->image('id_card.jpg');
    $contact->addMedia($file)->toMediaCollection('kyc_id_cards');
    
    $media = $contact->getFirstMedia('kyc_id_cards');
    expect($media->disk)->toBe('tenant');
});
