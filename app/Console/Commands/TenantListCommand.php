<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantListCommand extends Command
{
    protected $signature = 'tenant:list {--status= : Filter by status}';

    protected $description = 'List all tenants';

    public function handle(): int
    {
        $query = Tenant::on('pgsql')->with('domains');

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $tenants = $query->orderBy('created_at', 'desc')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Email', 'Status', 'Tier', 'Domains', 'Created'],
            $tenants->map(fn ($tenant) => [
                $tenant->id,
                $tenant->name,
                $tenant->slug,
                $tenant->email ?? '-',
                $tenant->status,
                $tenant->tier,
                $tenant->domains->pluck('domain')->join(', ') ?: '-',
                $tenant->created_at->format('Y-m-d H:i'),
            ])
        );

        $this->newLine();
        $this->info("Total: {$tenants->count()} tenant(s)");

        return self::SUCCESS;
    }
}
