<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Document;
use App\Models\User;

test('user can view documents list', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    Document::factory()->count(3)->create(['campaign_id' => $campaign->id]);

    loginAsUser($user)
        ->visit('/documents')
        ->assertUrlPath('/documents')
        ->assertSee('Documents');
})->group('documents');

test('user can view document details', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'original_filename' => 'test-document.pdf',
        'status' => 'completed',
    ]);

    loginAsUser($user)
        ->visit('/documents/'.$document->uuid)
        ->assertSee('test-document.pdf')
        ->assertSee('completed');
})->group('documents');

test('document shows processing status', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $document = Document::factory()->create([
        'campaign_id' => $campaign->id,
        'status' => 'processing',
    ]);

    loginAsUser($user)
        ->visit('/documents/'.$document->uuid)
        ->assertSee('processing')
        ->assertVisible('[data-testid="processing-status"]');
})->group('documents');

test('document list shows all campaign documents', function () {
    $user = User::factory()->create();
    $campaign1 = Campaign::factory()->create();
    $campaign2 = Campaign::factory()->create();

    Document::factory()->create([
        'campaign_id' => $campaign1->id,
        'original_filename' => 'doc1.pdf',
    ]);
    Document::factory()->create([
        'campaign_id' => $campaign2->id,
        'original_filename' => 'doc2.pdf',
    ]);

    loginAsUser($user)
        ->visit('/documents')
        ->assertSee('doc1.pdf')
        ->assertSee('doc2.pdf');
})->group('documents');

test('user can filter documents by status', function () {
    $user = User::factory()->create();
    $campaign = Campaign::factory()->create();

    Document::factory()->create([
        'campaign_id' => $campaign->id,
        'original_filename' => 'completed.pdf',
        'status' => 'completed',
    ]);
    Document::factory()->create([
        'campaign_id' => $campaign->id,
        'original_filename' => 'pending.pdf',
        'status' => 'pending',
    ]);

    loginAsUser($user)
        ->visit('/documents?status=completed')
        ->assertSee('completed.pdf')
        ->assertDontSee('pending.pdf');
})->group('documents');
