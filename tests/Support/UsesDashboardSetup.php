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
        // Create tenant on central DB
        $tenant = Tenant::factory()->create(array_merge([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'status' => 'active',
        ], $overrides));

        // Create or update test user on central DB and link to tenant
        $user = User::on('pgsql')->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'role' => 'owner',
            ]
        );
        $user->tenant_id = $tenant->id;
        $user->save();

        return [$tenant, $user];
    }
}
