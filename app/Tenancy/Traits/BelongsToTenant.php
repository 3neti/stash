<?php

declare(strict_types=1);

namespace App\Tenancy\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait for models that belong to a tenant.
 * 
 * Automatically sets the connection to 'tenant' for all queries.
 */
trait BelongsToTenant
{
    /**
     * Boot the tenant-aware trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        // Set connection on model initialization
        static::creating(function (Model $model) {
            if (!$model->getConnectionName()) {
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
