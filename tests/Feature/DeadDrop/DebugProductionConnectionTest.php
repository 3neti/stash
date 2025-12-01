<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

uses()->group('debug');

/**
 * URGENT DEBUG: Why is the middleware flow failing in production?
 * Tests pass but browser fails with "relation campaigns does not exist"
 */

test('debug: trace database connections during middleware flow', function () {
    // Setup
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->update(['tenant_id' => $tenant->id]);

    TenantContext::run($tenant, function () {
        Campaign::factory()->create(['name' => 'Debug Campaign']);
    });

    // Simulate middleware flow
    ray("=== MIDDLEWARE FLOW DEBUG ===");
    
    ray("Step 1: Middleware retrieves user");
    $retrievedUser = User::find($user->id);
    ray("User found: {$retrievedUser->id}, tenant_id: {$retrievedUser->tenant_id}");

    ray("Step 2: Middleware retrieves tenant");
    $retrievedTenant = Tenant::on('pgsql')->find($retrievedUser->tenant_id);
    ray("Tenant found: {$retrievedTenant->id}");

    ray("Step 3: Before TenantContext::initialize()");
    ray("  Current connection: " . DB::getDefaultConnection());
    ray("  pgsql database: " . config('database.connections.pgsql.database'));
    ray("  tenant database (before): " . config('database.connections.tenant.database'));

    // This is what middleware does
    TenantContext::initialize($retrievedTenant);

    ray("Step 4: After TenantContext::initialize()");
    ray("  Current connection: " . DB::getDefaultConnection());
    ray("  tenant database (after): " . config('database.connections.tenant.database'));

    // Check schema guard
    $manager = app(TenantConnectionManager::class);
    ray("Step 5: Schema guard check");
    ray("  Database exists: " . ($manager->tenantDatabaseExists($retrievedTenant) ? 'YES' : 'NO'));
    ray("  Schema initialized: " . ($manager->tenantSchemaInitialized($retrievedTenant) ? 'YES' : 'NO'));

    ray("Step 6: Try to query campaigns");
    try {
        $count = Campaign::count();
        ray("  SUCCESS: Found $count campaigns");
    } catch (\Exception $e) {
        ray("  ERROR: " . $e->getMessage());
        throw $e;
    } finally {
        TenantContext::forgetCurrent();
    }
});

test('debug: check if tenant database actually exists in production setup', function () {
    // Simulate fresh setup like: php artisan migrate:fresh && php artisan dashboard:setup-test
    $tenant = Tenant::factory()->create(['name' => 'Fresh Setup Test']);
    $manager = app(TenantConnectionManager::class);
    
    $dbName = $manager->getTenantDatabaseName($tenant);
    ray("Tenant ID: {$tenant->id}");
    ray("Expected DB name: $dbName");
    
    $exists = $manager->tenantDatabaseExists($tenant);
    ray("Database '$dbName' exists in pg_database: " . ($exists ? 'YES' : 'NO'));
    
    // Try to switch to it
    try {
        TenantContext::initialize($tenant);
        ray("TenantContext::initialize() succeeded");
        ray("Configured tenant connection to: " . config('database.connections.tenant.database'));
        
        // Try to query
        $campaignsTable = DB::connection('tenant')->select("SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND table_name='campaigns'");
        ray("Campaigns table exists: " . (count($campaignsTable) > 0 ? 'YES' : 'NO'));
    } catch (\Exception $e) {
        ray("ERROR: " . $e->getMessage());
    } finally {
        TenantContext::forgetCurrent();
    }
});
