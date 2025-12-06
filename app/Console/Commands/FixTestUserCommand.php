<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class FixTestUserCommand extends Command
{
    protected $signature = 'user:fix-test';

    protected $description = 'Fix test user tenant linkage after migrate:fresh';

    public function handle(): int
    {
        $user = User::on('central')->where('email', 'test@example.com')->first();
        $tenant = Tenant::on('central')->where('slug', 'test-company')->first();

        if (! $user) {
            $this->error('Test user not found');

            return 1;
        }

        if (! $tenant) {
            $this->error('Test tenant not found');

            return 1;
        }

        $this->info("Current state:");
        $this->line("  Email: {$user->email}");
        $this->line("  Tenant ID: ".($user->tenant_id ?? 'NULL'));
        $this->line("  Role: {$user->role}");

        $user->tenant_id = $tenant->id;
        $user->role = 'owner';
        $user->email_verified_at = now();
        $user->save();

        $this->newLine();
        $this->info('✅ User updated!');
        $this->line("  Tenant ID: {$user->tenant_id}");
        $this->line("  Role: {$user->role}");
        $this->newLine();
        $this->info('Dashboard should now work at http://stash.test');

        return 0;
    }
}
