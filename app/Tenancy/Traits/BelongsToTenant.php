<?php

declare(strict_types=1);

namespace App\Tenancy\Traits;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for models that belong to a tenant.
 *
 * Automatically sets the connection to 'tenant' for all queries
 * and populates tenant_id from the current tenant context.
 */
trait BelongsToTenant
{
    /**
     * Boot the tenant-aware trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Auto-populate tenant_id and set connection on model creation
        static::creating(function (Model $model) {
            // Auto-set tenant_id if:
            // 1. Model has tenant_id in fillable array (indicating it supports the column)
            // 2. tenant_id is not already set
            // 3. We're in a tenant context
            if (in_array('tenant_id', $model->getFillable()) && 
                empty($model->tenant_id) && 
                TenantContext::isInitialized()) {
                $tenant = TenantContext::current();
                if ($tenant) {
                    $model->tenant_id = $tenant->id;
                }
            }
            
            // Set connection to tenant database
            if (! $model->getConnectionName()) {
                $model->setConnection('tenant');
            }
        });
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName(): ?string
    {
        return parent::getConnectionName() ?? 'tenant';
    }
}
