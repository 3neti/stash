<?php
declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;


uses()->group('debug');

beforeEach(fn() => test()->markTestSkipped('Phase 7: Complex DeadDrop - debug test, needs investigation'));

/**
 * URGENT DEBUG: Why is the middleware flow failing in production?
 * Tests pass but browser fails with "relation campaigns does not exist"
 */

test('debug: trace database connections during middleware flow', function () {
    // Setup
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $tenant->users()->attach($user->id, ['role' => 'member']);

    TenantContext::run($tenant, function () {
        Campaign::factory()->create(['name' => 'Debug Campaign']);
    });

    // Simulate middleware flow
    $retrievedUser = User::find($user->id);
    $userTenant = $retrievedUser->tenants()->first();
    expect($userTenant)->not->toBeNull();
    expect($userTenant->id)->toBe($tenant->id);

    $retrievedTenant = Tenant::find($userTenant->id);
    expect($retrievedTenant->id)->toBe($tenant->id);

    // This is what middleware does
    TenantContext::initialize($retrievedTenant);

    // Check schema guard
    $manager = app(TenantConnectionManager::class);
    $databaseExists = $manager->tenantDatabaseExists($retrievedTenant);
    $schemaInitialized = $manager->tenantSchemaInitialized($retrievedTenant);

    expect($databaseExists)->toBeTrue('Tenant database should exist');
    expect($schemaInitialized)->toBeTrue('Tenant schema should be initialized');

    // Try to query campaigns
    try {
        $count = Campaign::count();
        expect($count)->toBeGreaterThanOrEqual(1);
    } finally {
        TenantContext::forgetCurrent();
    }
});

test('debug: check if tenant database actually exists in production setup', function () {
    // Simulate fresh setup like: php artisan migrate:fresh && php artisan dashboard:setup-test
    $tenant = Tenant::factory()->create(['name' => 'Fresh Setup Test']);
    $manager = app(TenantConnectionManager::class);
    
    $dbName = $manager->getTenantDatabaseName($tenant);
    expect($dbName)->toBeTruthy();
    
    // BEFORE TenantContext::initialize() - database should NOT exist yet
    $existsBeforeInit = $manager->tenantDatabaseExists($tenant);
    
    // AFTER TenantContext::initialize() - database and schema should be created automatically
    TenantContext::initialize($tenant);
    $existsAfterInit = $manager->tenantDatabaseExists($tenant);
    
    expect($existsAfterInit)->toBeTrue('Tenant database should exist after TenantContext::initialize()');
    
    // Try to query campaigns table
    try {
        $campaignsTable = DB::connection('tenant')->select("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='campaigns'");
        expect(count($campaignsTable))->toBeGreaterThan(0, 'Campaigns table should exist after schema initialized');
    } finally {
        TenantContext::forgetCurrent();
    }
});
