<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\UsageEvent;
use Illuminate\Support\Str;

it('can create a usage event with required fields', function () {
    $event = UsageEvent::create([
        'event_type' => 'upload',
        'units' => 1,
        'cost_credits' => 5,
    ]);

    expect($event)
        ->event_type->toBe('upload')
        ->units->toBe(1)
        ->cost_credits->toBe(5);
});

it('generates ULID automatically on creation', function () {
    $event = UsageEvent::create([
        'event_type' => 'ai_task',
        'units' => 100,
        'cost_credits' => 10,
    ]);

    expect($event->id)
        ->toBeString()
        ->not->toBeEmpty();
    expect(Str::isUlid($event->id))->toBeTrue();
});

it('sets recorded_at automatically on creation', function () {
    $event = UsageEvent::create([
        'event_type' => 'processor_execution',
        'units' => 1,
        'cost_credits' => 2,
    ]);

    expect($event->recorded_at)
        ->not->toBeNull()
        ->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can be created using factory', function () {
    $event = UsageEvent::factory()->create();

    expect($event->exists)->toBeTrue();
    expect(Str::isUlid($event->id))->toBeTrue();
});

it('can be created using factory with custom attributes', function () {
    $event = UsageEvent::factory()->create([
        'event_type' => 'storage',
        'units' => 50,
    ]);

    expect($event)
        ->event_type->toBe('storage')
        ->units->toBe(50);
});

it('can create upload event using factory state', function () {
    $event = UsageEvent::factory()->upload()->create();

    expect($event)
        ->event_type->toBe('upload')
        ->units->toBe(1)
        ->cost_credits->toBe(1);
});

it('can create ai_task event using factory state', function () {
    $event = UsageEvent::factory()->aiTask()->create();

    expect($event)
        ->event_type->toBe('ai_task');
    expect($event->metadata)->toHaveKey('model');
});

it('can create processor_execution event using factory state', function () {
    $event = UsageEvent::factory()->processorExecution()->create();

    expect($event)
        ->event_type->toBe('processor_execution')
        ->units->toBe(1);
});

it('casts metadata to array', function () {
    $metadata = [
        'model' => 'gpt-4',
        'tokens' => 1500,
        'provider' => 'openai',
    ];

    $event = UsageEvent::factory()->create([
        'metadata' => $metadata,
    ]);

    expect($event->metadata)
        ->toBeArray()
        ->toBe($metadata);
});

it('casts units to integer', function () {
    $event = UsageEvent::factory()->create(['units' => '100']);

    expect($event->units)->toBeInt();
    expect($event->units)->toBe(100);
});

it('casts cost_credits to integer', function () {
    $event = UsageEvent::factory()->create(['cost_credits' => '50']);

    expect($event->cost_credits)->toBeInt();
    expect($event->cost_credits)->toBe(50);
});

it('casts recorded_at to datetime', function () {
    $event = UsageEvent::factory()->create([
        'recorded_at' => '2024-01-15 10:30:00',
    ]);

    expect($event->recorded_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('has campaign relationship', function () {
    $campaign = Campaign::factory()->create();
    $event = UsageEvent::factory()->create(['campaign_id' => $campaign->id]);

    expect($event->campaign)->toBeInstanceOf(Campaign::class);
    expect($event->campaign->id)->toBe($campaign->id);
});

it('scopes events by event_type', function () {
    UsageEvent::factory()->create(['event_type' => 'upload']);
    UsageEvent::factory()->create(['event_type' => 'upload']);
    UsageEvent::factory()->create(['event_type' => 'ai_task']);

    $uploads = UsageEvent::byEventType('upload')->get();

    expect($uploads)->toHaveCount(2);
});

it('scopes events in period', function () {
    $start = now()->subDays(10);
    $end = now()->subDays(5);

    UsageEvent::factory()->create(['recorded_at' => now()->subDays(7)]);
    UsageEvent::factory()->create(['recorded_at' => now()->subDays(3)]);
    UsageEvent::factory()->create(['recorded_at' => now()->subDays(15)]);

    $events = UsageEvent::inPeriod($start, $end)->get();

    expect($events)->toHaveCount(1);
});

it('calculates total credits', function () {
    UsageEvent::factory()->create(['cost_credits' => 10]);
    UsageEvent::factory()->create(['cost_credits' => 20]);
    UsageEvent::factory()->create(['cost_credits' => 15]);

    $total = UsageEvent::totalCredits();

    expect($total)->toBe(45);
});

it('calculates total credits in period', function () {
    $start = now()->subDays(10);
    $end = now()->subDays(5);

    UsageEvent::factory()->create(['cost_credits' => 10, 'recorded_at' => now()->subDays(7)]);
    UsageEvent::factory()->create(['cost_credits' => 20, 'recorded_at' => now()->subDays(3)]);
    UsageEvent::factory()->create(['cost_credits' => 15, 'recorded_at' => now()->subDays(15)]);

    $total = UsageEvent::totalCredits($start, $end);

    expect($total)->toBe(10);
});

it('returns breakdown by event type', function () {
    UsageEvent::factory()->create(['event_type' => 'upload', 'units' => 1, 'cost_credits' => 5]);
    UsageEvent::factory()->create(['event_type' => 'upload', 'units' => 1, 'cost_credits' => 5]);
    UsageEvent::factory()->create(['event_type' => 'ai_task', 'units' => 100, 'cost_credits' => 50]);

    $breakdown = UsageEvent::breakdownByType();

    expect($breakdown)
        ->toHaveKey('upload')
        ->toHaveKey('ai_task');
    expect($breakdown['upload']['total_units'])->toBe(2);
    expect($breakdown['upload']['total_credits'])->toBe(10);
    expect($breakdown['ai_task']['total_units'])->toBe(100);
    expect($breakdown['ai_task']['total_credits'])->toBe(50);
});

it('returns breakdown by event type in period', function () {
    $start = now()->subDays(10);
    $end = now()->subDays(5);

    UsageEvent::factory()->create(['event_type' => 'upload', 'units' => 1, 'cost_credits' => 5, 'recorded_at' => now()->subDays(7)]);
    UsageEvent::factory()->create(['event_type' => 'upload', 'units' => 1, 'cost_credits' => 5, 'recorded_at' => now()->subDays(3)]);

    $breakdown = UsageEvent::breakdownByType($start, $end);

    expect($breakdown)->toHaveKey('upload');
    expect($breakdown['upload']['total_units'])->toBe(1);
    expect($breakdown['upload']['total_credits'])->toBe(5);
});
