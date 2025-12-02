<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration is a placeholder. Tenant ID management is handled by the BelongsToTenant trait
     * and tenant context initialization in middleware. No explicit tenant_id columns needed.
     */
    public function up(): void
    {
        // No operations needed
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No operations to reverse
    }
};
