<?php

namespace Tests\Feature\Smoke;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SetUpsTenantDatabase;
use Tests\TestCase;

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
        $user = User::factory()->create();
        
        expect($user)->not->toBeNull();
        expect($user->email)->not->toBeNull();
        expect($user->getConnectionName())->toBe('central');
    })->uses(TestCase::class, RefreshDatabase::class);

    test('can create tenant with helper', function () {
        $tenant = $this->createTenant(['name' => 'Smoke Test Org']);
        
        expect($tenant)->not->toBeNull();
        expect($tenant->name)->toBe('Smoke Test Org');
        expect($tenant->slug)->toContain('test-');
        expect($tenant->getConnectionName())->toBe('central');
    })->uses(TestCase::class, RefreshDatabase::class);

    test('can create user with tenant helper', function () {
        $tenant = $this->createTenant();
        $user = $this->createUserWithTenant($tenant);
        
        expect($user)->not->toBeNull();
        expect($user->tenants)->toHaveCount(1);
        expect($user->tenants->first()->id)->toBe($tenant->id);
        
        // Verify pivot data
        $pivot = $user->tenants->first()->pivot;
        expect($pivot->role)->toBe('admin');
    })->uses(TestCase::class, RefreshDatabase::class);

    test('can create campaign in tenant context', function () {
        $tenant = $this->createTenant();
        
        $campaign = $this->inTenantContext($tenant, function () {
            return Campaign::factory()->create(['name' => 'Smoke Test Campaign']);
        });
        
        expect($campaign)->not->toBeNull();
        expect($campaign->name)->toBe('Smoke Test Campaign');
        expect($campaign->getConnectionName())->toBe('tenant');
    })->uses(TestCase::class, SetUpsTenantDatabase::class);

    test('tenant database migrations run successfully', function () {
        $tenant = $this->createTenant();
        
        // Verify tenant tables exist by querying them
        $this->inTenantContext($tenant, function () {
            $campaigns = Campaign::all();
            expect($campaigns)->toBeCollection();
            expect($campaigns)->toHaveCount(0); // Fresh database
        });
    })->uses(TestCase::class, SetUpsTenantDatabase::class);

    test('can create multiple tenants with unique slugs', function () {
        $tenant1 = $this->createTenant(['name' => 'Tenant One']);
        $tenant2 = $this->createTenant(['name' => 'Tenant Two']);
        
        expect($tenant1->slug)->not->toBe($tenant2->slug);
        expect($tenant1->name)->toBe('Tenant One');
        expect($tenant2->name)->toBe('Tenant Two');
    })->uses(TestCase::class, RefreshDatabase::class);

    test('tenant context isolation works correctly', function () {
        $tenant1 = $this->createTenant(['name' => 'Tenant One']);
        $tenant2 = $this->createTenant(['name' => 'Tenant Two']);
        
        // Create campaign in tenant1
        $campaign1 = $this->inTenantContext($tenant1, function () {
            return Campaign::factory()->create(['name' => 'Campaign in Tenant 1']);
        });
        
        // Create campaign in tenant2
        $campaign2 = $this->inTenantContext($tenant2, function () {
            return Campaign::factory()->create(['name' => 'Campaign in Tenant 2']);
        });
        
        // Verify campaigns are in different tenants
        expect($campaign1->name)->toBe('Campaign in Tenant 1');
        expect($campaign2->name)->toBe('Campaign in Tenant 2');
        expect($campaign1->id)->not->toBe($campaign2->id);
    })->uses(TestCase::class, SetUpsTenantDatabase::class);
});
