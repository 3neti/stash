<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

trait UsesDashboardSetup
{
    /**
     * Create a tenant and test user on central DB.
     * Tests will use TenantContext::run() which handles switching to tenant connection.
     */
    protected function setupDashboardTestTenant(array $overrides = []): array
    {
        // Create tenant on central DB with unique slug per test
        $uniqueSlug = 'test-company-' . uniqid();
        $tenant = Tenant::factory()->create(array_merge([
            'name' => 'Test Company',
            'slug' => $uniqueSlug,
            'status' => 'active',
        ], $overrides));

        // Create or update test user on central DB
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
        
        // Attach user to tenant via pivot table if not already attached
        if (!$tenant->users()->where('user_id', $user->id)->exists()) {
            $tenant->users()->attach($user->id, ['role' => 'owner']);
        }

        return [$tenant, $user];
    }
}
