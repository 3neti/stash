<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a tenant is initialized.
 *
 * Use this event to bootstrap tenant-specific services (cache, filesystem, etc.)
 */
class TenantInitialized
{
    use Dispatchable, SerializesModels;

    public function __construct(public Tenant $tenant) {}
}
