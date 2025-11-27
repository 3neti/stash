<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            // Primary key (ULID generated in model)
            $table->string('id', 26)->primary();
            
            // Core tenant information
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'suspended', 'cancelled'])->default('active');
            $table->enum('tier', ['starter', 'professional', 'enterprise'])->default('starter');
            
            // Configuration and settings
            $table->json('settings')->nullable();
            $table->text('credentials')->nullable(); // Encrypted JSON
            
            // Billing and credits
            $table->unsignedBigInteger('credit_balance')->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            
            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
