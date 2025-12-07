<?php

declare(strict_types=1);

use App\Events\TenantOnboarded;
use App\Models\Tenant;
use App\Services\TenantOnboardingService;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

test('observer triggers onboarding when tenant created', function () {
    Config::set('app.tenant_auto_onboarding', true);
    Config::set('campaigns.default_templates', []); // Skip templates for speed

    // Create tenant - observer should fire automatically
    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-tenant-'.uniqid(),
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);

    // Verify side effects of onboarding
    $manager = app(TenantConnectionManager::class);
    expect($manager->tenantDatabaseExists($tenant))->toBeTrue()
        ->and($manager->tenantSchemaInitialized($tenant))->toBeTrue();
});

test('observer respects auto-onboarding config when enabled', function () {
    Config::set('app.tenant_auto_onboarding', true);
    Config::set('campaigns.default_templates', []);

    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-enabled-'.uniqid(),
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);
    
    // Verify database was created (proof onboarding ran)
    $manager = app(TenantConnectionManager::class);
    expect($manager->tenantDatabaseExists($tenant))->toBeTrue();
});

test('observer respects auto-onboarding config when disabled', function () {
    Config::set('app.tenant_auto_onboarding', false);

    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-disabled-'.uniqid(),
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);
    
    // Verify database was NOT created (proof onboarding didn't run)
    $manager = app(TenantConnectionManager::class);
    expect($manager->tenantDatabaseExists($tenant))->toBeFalse();
});

test('observer does not trigger on tenant update', function () {
    Config::set('app.tenant_auto_onboarding', false);

    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-update-'.uniqid(),
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);
    
    $manager = app(TenantConnectionManager::class);
    expect($manager->tenantDatabaseExists($tenant))->toBeFalse();
    
    // Now enable onboarding and update tenant
    Config::set('app.tenant_auto_onboarding', true);
    $tenant->update(['name' => 'Updated Name']);
    
    // Database should still not exist (observer only fires on 'created', not 'updated')
    expect($manager->tenantDatabaseExists($tenant))->toBeFalse();
});
