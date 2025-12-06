<?php

declare(strict_types=1);

use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Str;

it('can create an audit log with required fields', function () {
    $log = AuditLog::create([
        'auditable_type' => Campaign::class,
        'auditable_id' => '01test123',
        'event' => 'created',
    ]);

    expect($log)
        ->auditable_type->toBe(Campaign::class)
        ->event->toBe('created');
});

it('generates ULID automatically on creation', function () {
    $log = AuditLog::create([
        'auditable_type' => Campaign::class,
        'auditable_id' => '01test456',
        'event' => 'updated',
    ]);

    expect($log->id)
        ->toBeString()
        ->not->toBeEmpty();
    expect(Str::isUlid($log->id))->toBeTrue();
});

it('sets created_at automatically', function () {
    $log = AuditLog::create([
        'auditable_type' => Campaign::class,
        'auditable_id' => '01test789',
        'event' => 'created',
    ]);

    expect($log->created_at)
        ->not->toBeNull()
        ->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can be created using factory', function () {
    $log = AuditLog::factory()->create();

    expect($log->exists)->toBeTrue();
    expect(Str::isUlid($log->id))->toBeTrue();
});

it('can create created event using factory state', function () {
    $log = AuditLog::factory()->created()->create();

    expect($log)
        ->event->toBe('created')
        ->old_values->toBeNull();
});

it('can create updated event using factory state', function () {
    $log = AuditLog::factory()->updated()->create();

    expect($log)
        ->event->toBe('updated')
        ->old_values->not->toBeNull()
        ->new_values->not->toBeNull();
});

it('can create deleted event using factory state', function () {
    $log = AuditLog::factory()->deleted()->create();

    expect($log)
        ->event->toBe('deleted')
        ->new_values->toBeNull();
});

it('casts old_values to array', function () {
    $oldValues = ['state' => \App\States\Campaign\DraftCampaignState::class, 'name' => 'Old Name'];

    $log = AuditLog::factory()->create([
        'old_values' => $oldValues,
    ]);

    expect($log->old_values)
        ->toBeArray()
        ->toBe($oldValues);
});

it('casts new_values to array', function () {
    $newValues = ['status' => 'active', 'name' => 'New Name'];

    $log = AuditLog::factory()->create([
        'new_values' => $newValues,
    ]);

    expect($log->new_values)
        ->toBeArray()
        ->toBe($newValues);
});

it('casts tags to array', function () {
    $tags = ['security', 'compliance'];

    $log = AuditLog::factory()->create([
        'tags' => $tags,
    ]);

    expect($log->tags)
        ->toBeArray()
        ->toBe($tags);
});

it('has polymorphic auditable relationship', function () {
    $campaign = Campaign::factory()->create();

    $log = AuditLog::factory()->create([
        'auditable_type' => Campaign::class,
        'auditable_id' => $campaign->id,
    ]);

    expect($log->auditable())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\MorphTo::class);
});

it('can retrieve auditable entity', function () {
    $campaign = Campaign::factory()->create();

    $log = AuditLog::factory()->create([
        'auditable_type' => Campaign::class,
        'auditable_id' => $campaign->id,
    ]);

    $log->load('auditable');

    expect($log->auditable)->toBeInstanceOf(Campaign::class);
    expect($log->auditable->id)->toBe($campaign->id);
});

it('scopes logs by user', function () {
    $user = User::factory()->connection('central')->create();

    AuditLog::factory()->create(['user_id' => $user->id]);
    AuditLog::factory()->create(['user_id' => $user->id]);
    AuditLog::factory()->create(['user_id' => null]);

    $userLogs = AuditLog::byUser($user->id)->get();

    expect($userLogs)->toHaveCount(2);
});

it('scopes logs by event', function () {
    AuditLog::factory()->create(['event' => 'created']);
    AuditLog::factory()->create(['event' => 'created']);
    AuditLog::factory()->create(['event' => 'updated']);

    $createdLogs = AuditLog::byEvent('created')->get();

    expect($createdLogs)->toHaveCount(2);
});

it('scopes logs by auditable type and id', function () {
    $campaign = Campaign::factory()->create();

    AuditLog::factory()->create([
        'auditable_type' => Campaign::class,
        'auditable_id' => $campaign->id,
    ]);
    AuditLog::factory()->create([
        'auditable_type' => Campaign::class,
        'auditable_id' => '01other123',
    ]);

    $campaignLogs = AuditLog::byAuditable(Campaign::class, $campaign->id)->get();

    expect($campaignLogs)->toHaveCount(1);
});

it('scopes logs in period', function () {
    $start = now()->subDays(10);
    $end = now()->subDays(5);

    // Use raw DB insert to bypass boot() hook
    \Illuminate\Support\Facades\DB::connection('tenant')->table('audit_logs')->insert([
        ['id' => (string) \Illuminate\Support\Str::ulid(), 'auditable_type' => Campaign::class, 'auditable_id' => '01test1', 'event' => 'created', 'created_at' => now()->subDays(7)],
        ['id' => (string) \Illuminate\Support\Str::ulid(), 'auditable_type' => Campaign::class, 'auditable_id' => '01test2', 'event' => 'created', 'created_at' => now()->subDays(3)],
        ['id' => (string) \Illuminate\Support\Str::ulid(), 'auditable_type' => Campaign::class, 'auditable_id' => '01test3', 'event' => 'created', 'created_at' => now()->subDays(15)],
    ]);

    $logs = AuditLog::inPeriod($start, $end)->get();

    expect($logs)->toHaveCount(1);
});

it('scopes logs with specific tag', function () {
    AuditLog::factory()->create(['tags' => ['security', 'compliance']]);
    AuditLog::factory()->create(['tags' => ['security']]);
    AuditLog::factory()->create(['tags' => ['audit']]);

    $securityLogs = AuditLog::withTag('security')->get();

    expect($securityLogs)->toHaveCount(2);
});

it('prevents updates to audit logs', function () {
    $log = AuditLog::factory()->create(['event' => 'created']);

    $result = $log->update(['event' => 'updated']);

    expect($result)->toBeFalse();
    $log->refresh();
    expect($log->event)->toBe('created');
});

it('prevents deletes of audit logs', function () {
    $log = AuditLog::factory()->create();
    $id = $log->id;

    $result = $log->delete();

    expect($result)->toBeFalse();
    expect(AuditLog::find($id))->not->toBeNull();
});

it('creates audit log using static log method', function () {
    $campaign = Campaign::factory()->create();
    $user = User::factory()->connection('central')->create();

    $log = AuditLog::log(
        auditableType: Campaign::class,
        auditableId: $campaign->id,
        event: 'published',
        oldValues: ['state' => \App\States\Campaign\DraftCampaignState::class],
        newValues: ['status' => 'active'],
        userId: $user->id,
        tags: ['campaign', 'publish']
    );

    expect($log)->toBeInstanceOf(AuditLog::class);
    expect($log->event)->toBe('published');
    expect($log->user_id)->toBe($user->id);
    expect($log->tags)->toBe(['campaign', 'publish']);
});
