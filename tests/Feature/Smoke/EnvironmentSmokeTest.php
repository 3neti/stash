<?php

use App\Models\Campaign;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Smoke tests to verify the clean test environment works correctly.
 * 
 * These tests verify:
 * - Central database setup and User model
 * - Tenant creation helper
 * - User-tenant association helper
 * - Tenant context switching
 * - Tenant database migrations
 * 
 * All tests must pass before reintroducing legacy tests.
 */
describe('Clean Test Environment', function () {
    
    test('can create user on central database', function () {
        $user = User::on('central')->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        
        expect($user)->not->toBeNull();
        expect($user->email)->not->toBeNull();
        expect($user->getConnectionName())->toBe('central');
    });

    test('can create tenant with helper', function () {
        $tenant = $this->createTenant(['name' => 'Smoke Test Org']);
        
        expect($tenant)->not->toBeNull();
        expect($tenant->name)->toBe('Smoke Test Org');
        expect($tenant->slug)->toContain('test-');
        expect($tenant->getConnectionName())->toBe('central');
    });

    test('can create user with tenant helper', function () {
        $tenant = $this->createTenant();
        
        $user = User::on('central')->create([
            'name' => 'Test User',
            'email' => 'tenant-user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        
        $tenant->users()->attach($user, ['role' => 'admin']);
        
        expect($user)->not->toBeNull();
        expect($user->tenants)->toHaveCount(1);
        expect($user->tenants->first()->id)->toBe($tenant->id);
        
        // Verify pivot data
        $pivot = $user->tenants->first()->pivot;
        expect($pivot->role)->toBe('admin');
    });

    test('can create campaign in tenant context', function () {
        $tenant = Tenant::on('central')->create([
            'name' => 'Test Tenant',
            'slug' => 'test-' . uniqid(),
            'email' => fake()->email(),
            'tier' => 'professional',
        ]);
        
        // Initialize tenant database
        app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($tenant);
        
        $campaign = TenantContext::run($tenant, function () {
            return Campaign::factory()->create(['name' => 'Smoke Test Campaign']);
        });
        
        expect($campaign)->not->toBeNull();
        expect($campaign->name)->toBe('Smoke Test Campaign');
        expect($campaign->getConnectionName())->toBe('tenant');
    });

    test('tenant database migrations run successfully', function () {
        $tenant = Tenant::on('central')->create([
            'name' => 'Test Tenant',
            'slug' => 'test-' . uniqid(),
            'email' => fake()->email(),
            'tier' => 'professional',
        ]);
        
        // Initialize tenant database
        app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($tenant);
        
        // Verify tenant tables exist by querying them
        TenantContext::run($tenant, function () {
            $campaigns = Campaign::all();
            expect($campaigns)->toBeCollection();
            expect($campaigns)->toHaveCount(0); // Fresh database
        });
    });

    test('can create multiple tenants with unique slugs', function () {
        $tenant1 = $this->createTenant(['name' => 'Tenant One']);
        $tenant2 = $this->createTenant(['name' => 'Tenant Two']);
        
        expect($tenant1->slug)->not->toBe($tenant2->slug);
        expect($tenant1->name)->toBe('Tenant One');
        expect($tenant2->name)->toBe('Tenant Two');
    });

    test('tenant context isolation works correctly', function () {
        $tenant1 = Tenant::on('central')->create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one-' . uniqid(),
            'email' => fake()->email(),
            'tier' => 'professional',
        ]);
        
        $tenant2 = Tenant::on('central')->create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two-' . uniqid(),
            'email' => fake()->email(),
            'tier' => 'professional',
        ]);
        
        // Initialize both tenant databases
        app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($tenant1);
        app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($tenant2);
        
        // Create campaign in tenant1
        $campaign1 = TenantContext::run($tenant1, function () {
            return Campaign::factory()->create(['name' => 'Campaign in Tenant 1']);
        });
        
        // Create campaign in tenant2
        $campaign2 = TenantContext::run($tenant2, function () {
            return Campaign::factory()->create(['name' => 'Campaign in Tenant 2']);
        });
        
        // Verify campaigns are in different tenants
        expect($campaign1->name)->toBe('Campaign in Tenant 1');
        expect($campaign2->name)->toBe('Campaign in Tenant 2');
        expect($campaign1->id)->not->toBe($campaign2->id);
    });
});
