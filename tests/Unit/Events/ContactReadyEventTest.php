<?php

declare(strict_types=1);

use App\Data\ContactData;
use App\Events\ContactReady;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    // Create tenant and initialize context
    $this->tenant = Tenant::create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-' . uniqid(),
        'database' => 'tenant_test',
    ]);
    
    app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($this->tenant);
    
    // Create a test contact
    $this->contact = Contact::create([
        'name' => 'Test User',
        'kyc_transaction_id' => 'TEST-123',
        'kyc_status' => 'approved',
        'kyc_completed_at' => now(),
    ]);
    
    $this->transactionId = 'TEST-123';
});

test('event broadcasts on correct channel', function () {
    $event = new ContactReady($this->contact, $this->transactionId);
    
    $channels = $event->broadcastOn();
    
    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe("kyc.{$this->transactionId}");
});

test('event has correct broadcast name', function () {
    $event = new ContactReady($this->contact, $this->transactionId);
    
    expect($event->broadcastAs())->toBe('contact.ready');
});

test('event broadcast data includes contact data', function () {
    $event = new ContactReady($this->contact, $this->transactionId);
    
    $broadcastData = $event->broadcastWith();
    
    expect($broadcastData)->toHaveKey('contact')
        ->and($broadcastData['contact'])->toBeInstanceOf(ContactData::class)
        ->and($broadcastData['contact']->id)->toBe($this->contact->id)
        ->and($broadcastData['contact']->kyc_status)->toBe('approved');
});

test('event implements ShouldBroadcastNow', function () {
    $event = new ContactReady($this->contact, $this->transactionId);
    
    expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
});

test('event has afterCommit flag enabled', function () {
    $event = new ContactReady($this->contact, $this->transactionId);
    
    expect($event->afterCommit)->toBeTrue();
});

test('event can be dispatched', function () {
    Event::fake();
    
    ContactReady::dispatch($this->contact, $this->transactionId);
    
    Event::assertDispatched(ContactReady::class, function ($event) {
        return $event->contact->id === $this->contact->id
            && $event->transactionId === $this->transactionId;
    });
});
