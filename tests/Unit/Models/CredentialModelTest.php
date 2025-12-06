<?php

declare(strict_types=1);

use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

use App\Models\Campaign;
use App\Models\Credential;
use App\Models\Processor;
use Illuminate\Support\Str;

it('can create a credential with required fields', function () {
    $credential = Credential::create([
        'key' => 'openai_api_key',
        'value' => 'sk-test123',
        'provider' => 'openai',
    ]);

    expect($credential)
        ->key->toBe('openai_api_key')
        ->provider->toBe('openai')
        ->credentialable_type->toBeNull()
        ->credentialable_id->toBeNull();
});

it('generates ULID automatically on creation', function () {
    $credential = Credential::create([
        'key' => 'test_key',
        'value' => 'test_value',
    ]);

    expect($credential->id)
        ->toBeString()
        ->not->toBeEmpty();
    expect(Str::isUlid($credential->id))->toBeTrue();
});

it('sets default is_active to true', function () {
    $credential = Credential::create([
        'key' => 'test_key',
        'value' => 'test_value',
    ]);

    expect($credential->is_active)->toBeTrue();
});

it('encrypts value on creation', function () {
    $plainValue = 'secret-api-key-12345';

    $credential = Credential::create([
        'key' => 'api_key',
        'value' => $plainValue,
    ]);

    // Raw database value should be encrypted
    $raw = \Illuminate\Support\Facades\DB::connection('tenant')
        ->table('credentials')
        ->where('id', $credential->id)
        ->value('value');

    expect($raw)->not->toBe($plainValue);
});

it('decrypts value when accessed', function () {
    $plainValue = 'secret-api-key-67890';

    $credential = Credential::create([
        'key' => 'api_key',
        'value' => $plainValue,
    ]);

    expect($credential->value)->toBe($plainValue);
});

it('can be created using factory', function () {
    $credential = Credential::factory()->create();

    expect($credential->exists)->toBeTrue();
    expect(Str::isUlid($credential->id))->toBeTrue();
});

it('can create system-scoped credential using factory state', function () {
    $credential = Credential::factory()->system()->create();

    expect($credential)
        ->credentialable_type->toBeNull()
        ->credentialable_id->toBeNull();
});

it('has polymorphic credentialable relationship', function () {
    $campaign = Campaign::factory()->create();
    $credential = Credential::factory()->forCampaign($campaign)->create();

    expect($credential->credentialable)->toBeInstanceOf(Campaign::class);
    expect($credential->credentialable->id)->toBe($campaign->id);
});

it('can create campaign-scoped credential', function () {
    $campaign = Campaign::factory()->create();
    $credential = Credential::factory()->forCampaign($campaign)->create();

    expect($credential)
        ->credentialable_type->toBe(Campaign::class)
        ->credentialable_id->toBe($campaign->id);
});

it('can create processor-scoped credential', function () {
    $processor = Processor::factory()->create();
    $credential = Credential::factory()->forProcessor($processor)->create();

    expect($credential)
        ->credentialable_type->toBe(Processor::class)
        ->credentialable_id->toBe($processor->id);
});

it('can create expired credential using factory state', function () {
    $credential = Credential::factory()->expired()->create();

    expect($credential->isExpired())->toBeTrue();
});

it('casts metadata to array', function () {
    $metadata = ['description' => 'Test credential', 'region' => 'us-east-1'];

    $credential = Credential::factory()->create([
        'metadata' => $metadata,
    ]);

    expect($credential->metadata)
        ->toBeArray()
        ->toBe($metadata);
});

it('casts expires_at to datetime', function () {
    $credential = Credential::factory()->create([
        'expires_at' => '2025-12-31 23:59:59',
    ]);

    expect($credential->expires_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('casts last_used_at to datetime', function () {
    $credential = Credential::factory()->create();
    $credential->markUsed();

    expect($credential->last_used_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('casts is_active to boolean', function () {
    $credential = Credential::factory()->create(['is_active' => 1]);

    expect($credential->is_active)->toBeBool();
    expect($credential->is_active)->toBeTrue();
});

it('scopes active credentials', function () {
    Credential::factory()->create(['is_active' => true]);
    Credential::factory()->create(['is_active' => true]);
    Credential::factory()->create(['is_active' => false]);

    $active = Credential::active()->get();

    expect($active)->toHaveCount(2);
});

it('scopes credentials by model', function () {
    $campaign = Campaign::factory()->create();

    Credential::factory()->forCampaign($campaign)->create();
    Credential::factory()->system()->create();

    $campaignCreds = Credential::forModel($campaign)->get();

    expect($campaignCreds)->toHaveCount(1);
});

it('scopes credentials by key', function () {
    Credential::factory()->create(['key' => 'openai_api_key']);
    Credential::factory()->create(['key' => 'openai_api_key']);
    Credential::factory()->create(['key' => 'anthropic_api_key']);

    $openaiCreds = Credential::forKey('openai_api_key')->get();

    expect($openaiCreds)->toHaveCount(2);
});

it('scopes non-expired credentials', function () {
    Credential::factory()->create(['expires_at' => now()->addDays(10)]);
    Credential::factory()->create(['expires_at' => null]);
    Credential::factory()->expired()->create();

    $notExpired = Credential::notExpired()->get();

    expect($notExpired)->toHaveCount(2);
});

it('returns true when credential is expired', function () {
    $credential = Credential::factory()->expired()->create();

    expect($credential->isExpired())->toBeTrue();
});

it('returns false when credential is not expired', function () {
    $credential = Credential::factory()->create(['expires_at' => now()->addDays(10)]);

    expect($credential->isExpired())->toBeFalse();
});

it('returns false when credential has no expiration', function () {
    $credential = Credential::factory()->create(['expires_at' => null]);

    expect($credential->isExpired())->toBeFalse();
});

it('returns true when credential is active and not expired', function () {
    $credential = Credential::factory()->create([
        'is_active' => true,
        'expires_at' => now()->addDays(10),
    ]);

    expect($credential->isActive())->toBeTrue();
});

it('returns false when credential is inactive', function () {
    $credential = Credential::factory()->create(['is_active' => false]);

    expect($credential->isActive())->toBeFalse();
});

it('returns false when credential is expired', function () {
    $credential = Credential::factory()->expired()->create(['is_active' => true]);

    expect($credential->isActive())->toBeFalse();
});

it('marks credential as used', function () {
    $credential = Credential::factory()->create(['last_used_at' => null]);

    expect($credential->last_used_at)->toBeNull();

    $credential->markUsed();
    $credential->refresh();

    expect($credential->last_used_at)->not->toBeNull();
});

it('resolves processor-scoped credential first', function () {
    $processor = Processor::factory()->create();

    Credential::factory()->system()->create(['key' => 'api_key', 'value' => 'system-key']);
    Credential::factory()->forProcessor($processor)->create([
        'key' => 'api_key',
        'value' => 'processor-key',
    ]);

    $resolved = Credential::resolve('api_key', processor: $processor);

    expect($resolved->value)->toBe('processor-key');
});

it('resolves campaign-scoped credential when processor not found', function () {
    $campaign = Campaign::factory()->create();

    Credential::factory()->system()->create(['key' => 'api_key', 'value' => 'system-key']);
    Credential::factory()->forCampaign($campaign)->create([
        'key' => 'api_key',
        'value' => 'campaign-key',
    ]);

    $resolved = Credential::resolve('api_key', campaign: $campaign);

    expect($resolved->value)->toBe('campaign-key');
});

it('resolves system-scoped credential as fallback', function () {
    Credential::factory()->system()->create(['key' => 'api_key', 'value' => 'system-key']);

    $resolved = Credential::resolve('api_key');

    expect($resolved)->not->toBeNull();
    expect($resolved->value)->toBe('system-key');
});

it('returns null when no credential found', function () {
    $resolved = Credential::resolve('nonexistent_key');

    expect($resolved)->toBeNull();
});

it('supports soft deletes', function () {
    $credential = Credential::factory()->create();
    $id = $credential->id;

    $credential->delete();

    expect(Credential::find($id))->toBeNull();
    expect(Credential::withTrashed()->find($id))->not->toBeNull();
});
