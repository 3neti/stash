<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a tenant database has been successfully prepared.
 *
 * This event is dispatched after:
 * - Database has been created (if needed)
 * - All migrations have been run
 * - Schema has been verified as initialized
 *
 * Use this event to bootstrap tenant-specific services that depend on schema.
 */
class TenantDatabasePrepared
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tenant $tenant) {}
}
