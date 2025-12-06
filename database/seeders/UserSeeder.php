<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user from .env if configured
        $adminEmail = env('ADMIN_EMAIL');
        $adminName = env('ADMIN_NAME', 'Admin User');
        $adminPassword = env('ADMIN_PASSWORD', 'password');

        if ($adminEmail) {
            $admin = User::firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => Hash::make($adminPassword),
                    'email_verified_at' => now(),
                ]
            );

            if ($admin->wasRecentlyCreated) {
                $this->command->info("✓ Admin user created: {$adminEmail}");
            } else {
                $this->command->info("✓ Admin user exists: {$adminEmail}");
            }
            
            // Note: Attach to tenant in TenantSeeder after tenants are created
        }

        // Create test user for browser testing
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create additional test users
        User::factory(5)->create();
    }
}
