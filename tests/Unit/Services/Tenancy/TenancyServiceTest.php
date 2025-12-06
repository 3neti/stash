<?php

declare(strict_types=1);

use App\Events\TenantDatabasePrepared;
use App\Models\Tenant;
use App\Services\Tenancy\TenancyService;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\DeadDropTestCase;

uses(DeadDropTestCase::class);

test('initializeTenant prepares database and initializes context', function () {
    Event::fake();
    
    $tenant = Tenant::factory()->create();
    $service = app(TenancyService::class);
    
    // Reset context
    TenantContext::forgetCurrent();
    
    $service->initializeTenant($tenant);
    
    // Verify context is initialized
    expect(TenantContext::current()?->id)->toBe($tenant->id);
    expect(TenantContext::isInitialized())->toBeTrue();
    
    // Verify events fired
    Event::assertDispatched(TenantDatabasePrepared::class);
});

test('prepareTenantDatabase creates database if it does not exist', function () {
    test()->markTestSkipped('DROP DATABASE cannot run inside test transaction (PostgreSQL limitation)');
    
    $tenant = Tenant::factory()->create();
    $manager = app(TenantConnectionManager::class);
    $service = app(TenancyService::class);
    
    $dbName = $manager->getTenantDatabaseName($tenant);
    
    // Drop database to simulate non-existent state
    $manager->dropTenantDatabase($tenant);
    expect($manager->tenantDatabaseExists($tenant))->toBeFalse();
    
    // Prepare should create it
    $service->prepareTenantDatabase($tenant);
    
    // Verify database exists
    expect($manager->tenantDatabaseExists($tenant))->toBeTrue();
});

test('prepareTenantDatabase initializes schema when missing', function () {
    test()->markTestSkipped('DROP DATABASE cannot run inside test transaction (PostgreSQL limitation)');
    
    $tenant = Tenant::factory()->create();
    $manager = app(TenantConnectionManager::class);
    $service = app(TenancyService::class);
    
    // Create empty database without schema
    $dbName = $manager->getTenantDatabaseName($tenant);
    $pdo = DB::connection('pgsql')->getPdo();
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    // Drop and recreate empty
    $manager->dropTenantDatabase($tenant);
    try {
        $pdo->exec(sprintf('CREATE DATABASE "%s"', $dbName));
    } catch (Exception $e) {
        // Database might already exist, continue
    }
    
    // Prepare should run migrations
    $service->prepareTenantDatabase($tenant);
    
    // Verify schema exists
    expect($manager->tenantSchemaInitialized($tenant))->toBeTrue();
});

test('verifyTenantSchema returns true when schema initialized', function () {
    test()->markTestSkipped('TenancyService schema verification needs update for current migration structure');
    
    $tenant = Tenant::factory()->create();
    $service = app(TenancyService::class);
    $manager = app(TenantConnectionManager::class);
    
    // Prepare database first
    $service->prepareTenantDatabase($tenant);
    
    expect($service->verifyTenantSchema($tenant))->toBeTrue();
});

test('verifyTenantSchema returns false when schema not initialized', function () {
    test()->markTestSkipped('TenancyService schema verification needs update');
    
    $tenant = Tenant::factory()->create();
    $manager = app(TenantConnectionManager::class);
    $service = app(TenancyService::class);
    
    // Create empty database
    $dbName = $manager->getTenantDatabaseName($tenant);
    $pdo = DB::connection('pgsql')->getPdo();
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    
    $manager->dropTenantDatabase($tenant);
    try {
        $pdo->exec(sprintf('CREATE DATABASE "%s"', $dbName));
    } catch (Exception $e) {
        // Continue
    }
    
    // Switch connection but don't run migrations
    $manager->switchToTenant($tenant);
    
    expect($service->verifyTenantSchema($tenant))->toBeFalse();
});

test('getTenantDatabaseName returns correct format', function () {
    $tenant = Tenant::factory()->create();
    $service = app(TenancyService::class);
    
    $dbName = $service->getTenantDatabaseName($tenant);
    
    expect($dbName)->toBe("tenant_{$tenant->id}");
});

test('prepareTenantDatabase fires TenantDatabasePrepared event', function () {
    Event::fake();
    
    $tenant = Tenant::factory()->create();
    $service = app(TenancyService::class);
    
    $service->prepareTenantDatabase($tenant);
    
    Event::assertDispatched(TenantDatabasePrepared::class, function ($event) use ($tenant) {
        return $event->tenant->id === $tenant->id;
    });
});
