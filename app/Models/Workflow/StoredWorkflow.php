<?php

declare(strict_types=1);

namespace App\Models\Workflow;

use Workflow\Models\StoredWorkflow as BaseStoredWorkflow;

/**
 * Custom StoredWorkflow model that forces 'central' database connection.
 *
 * Laravel Workflow uses the default connection, but in our multi-tenant
 * setup, we need workflows to always use the central database.
 */
class StoredWorkflow extends BaseStoredWorkflow
{
    protected $connection = 'central'; // Always use central database for workflows
}
