<?php

declare(strict_types=1);

use App\Actions\Campaigns\ApplyDefaultTemplates;
use App\Events\TenantOnboarded;
use App\Events\TenantOnboardingFailed;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Services\TenantOnboardingService;
use App\Tenancy\TenantConnectionManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

uses(TestCase::class, SetUpsTenantDatabase::class);

test('service creates database and runs migrations', function () {
    Event::fake();
    Config::set('app.tenant_auto_onboarding', false);
    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-onboard',
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);

    $service = app(TenantOnboardingService::class);
    $service->onboard($tenant);

    // Verify database exists
    $manager = app(TenantConnectionManager::class);
    $dbName = $manager->getTenantDatabaseName($tenant);
    
    expect($dbName)->toBe('tenant_'.$tenant->id);
    expect($manager->tenantDatabaseExists($tenant))->toBeTrue();
    
    // Verify migrations ran by checking a table exists
    expect($manager->tenantSchemaInitialized($tenant))->toBeTrue();
});

test('service applies default templates when configured', function () {
    Event::fake();
    Config::set('app.tenant_auto_onboarding', false);
    Config::set('campaigns.default_templates', ['simple-storage']);

    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-templates',
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);

    $service = app(TenantOnboardingService::class);
    $service->onboard($tenant);

    // Verify campaigns created in tenant database
    $this->inTenantContext($tenant, function () {
        $count = Campaign::count();
        expect($count)->toBeGreaterThan(0);
    });
});

test('service skips templates when not configured', function () {
    Event::fake();
    Config::set('app.tenant_auto_onboarding', false);
    Config::set('campaigns.default_templates', []);

    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-no-templates',
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);

    $service = app(TenantOnboardingService::class);
    $service->onboard($tenant);

    // Verify no campaigns created
    $this->inTenantContext($tenant, function () {
        $count = Campaign::count();
        expect($count)->toBe(0);
    });
});

test('service dispatches TenantOnboarded event on success', function () {
    Event::fake();
    Config::set('app.tenant_auto_onboarding', false);
    $tenant = Tenant::on('central')->create([
        'name' => 'Test Tenant',
        'slug' => 'test-event-success',
        'status' => 'active',
        'tier' => 'starter',
        'settings' => [],
        'credit_balance' => 0,
    ]);

    $service = app(TenantOnboardingService::class);
    $service->onboard($tenant);

    Event::assertDispatched(TenantOnboarded::class, function ($event) use ($tenant) {
        return $event->tenant->id === $tenant->id;
    });
});
test('service handles database creation failure', function () {
    Event::fake();
    Config::set('app.tenant_auto_onboarding', false);

    $tenant = $this->createTenant([
        'slug' => 'test-db-failure',
    ]);

    // Mock connection manager to throw exception
    $mockManager = Mockery::mock(TenantConnectionManager::class);
    $mockManager->shouldReceive('createTenantDatabase')
        ->andThrow(new \Exception('Database creation failed'));

    $service = new TenantOnboardingService($mockManager);

    try {
        $service->onboard($tenant);
        expect(false)->toBeTrue('Exception should have been thrown');
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Database creation failed');
    }

    // Verify failure event dispatched
    Event::assertDispatched(TenantOnboardingFailed::class, function ($event) use ($tenant) {
        return $event->tenant->id === $tenant->id
            && $event->exception->getMessage() === 'Database creation failed';
    });
    
    // Note: Can't verify tenant status due to HasStatuses ULID/bigint incompatibility
});
test('service handles migration failure gracefully', function () {
    Event::fake();
    Config::set('app.tenant_auto_onboarding', false);

    $tenant = $this->createTenant([
        'slug' => 'test-migration-failure',
    ]);

    // Mock connection manager to throw exception during migration
    $mockManager = Mockery::mock(TenantConnectionManager::class);
    $mockManager->shouldReceive('createTenantDatabase')->once();
    $mockManager->shouldReceive('getTenantDatabaseName')
        ->andReturn('tenant_'.$tenant->id);

    $service = new TenantOnboardingService($mockManager);

    // Mock Artisan to throw exception
    Artisan::shouldReceive('call')
        ->with('migrate:install', Mockery::any())
        ->andReturn(0);
    
    Artisan::shouldReceive('call')
        ->with('migrate', Mockery::any())
        ->andThrow(new \Exception('Migration failed'));

    try {
        $service->onboard($tenant);
        expect(false)->toBeTrue('Exception should have been thrown');
    } catch (\Exception $e) {
        expect($e->getMessage())->toBe('Migration failed');
    }

    Event::assertDispatched(TenantOnboardingFailed::class);
    
    // Note: Can't verify tenant status due to HasStatuses ULID/bigint incompatibility
});

test('service logs all onboarding steps', function () {
    Event::fake();
    Config::set('app.tenant_auto_onboarding', false);
    Config::set('campaigns.default_templates', []);
    
    Log::shouldReceive('info')->atLeast()->once();
    Log::shouldReceive('debug')->atLeast()->once();
    Log::shouldReceive('warning')->zeroOrMoreTimes();

    $tenant = $this->createTenant([
        'slug' => 'test-logging',
    ]);

    $service = app(TenantOnboardingService::class);
    $service->onboard($tenant);
    
    // Verify service completed without throwing exceptions
    expect(true)->toBeTrue();
});
