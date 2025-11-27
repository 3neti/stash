<?php

use App\Models\Document;
use App\States\Document\PendingDocumentState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Ensure tenant migrations are run
    DB::setDefaultConnection('pgsql');
    
    if (!DB::getSchemaBuilder()->hasTable('documents')) {
        Artisan::call('migrate', [
            '--database' => 'pgsql',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
});

test('it can create document with explicit attributes', function () {
    $document = Document::on('pgsql')->create([
        'uuid' => '123e4567-e89b-12d3-a456-426614174000',
        'campaign_id' => '01KB3T0000000000000000001', // Fake ULID
        'original_filename' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'storage_path' => 'test.pdf',
        'hash' => 'abc123',
    ]);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->id)->not->toBeNull();
});

test('it initializes with pending state', function () {
    $document = Document::on('pgsql')->create([
        'uuid' => '123e4567-e89b-12d3-a456-426614174001',
        'campaign_id' => '01KB3T0000000000000000001',
        'original_filename' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'storage_path' => 'test.pdf',
        'hash' => 'abc123',
    ]);

    expect($document->status)->toBeInstanceOf(PendingDocumentState::class);
});

test('it can make document without saving', function () {
    $document = Document::on('pgsql')->make([
        'uuid' => '123e4567-e89b-12d3-a456-426614174002',
        'campaign_id' => '01KB3T0000000000000000001',
        'original_filename' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'storage_path' => 'test.pdf',
        'hash' => 'abc123',
    ]);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->exists)->toBeFalse();
});
