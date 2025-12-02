<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            // Primary key
            $table->ulid('id')->primary();

            // Core campaign information
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('state');
            $table->enum('type', ['template', 'custom', 'meta'])->default('custom');

            // Pipeline and workflow configuration
            $table->json('pipeline_config'); // Processor graph definition
            $table->json('checklist_template')->nullable(); // Checklist items
            $table->json('settings')->nullable(); // Queue, AI routing, file rules

            // Credential overrides (encrypted)
            $table->text('credentials')->nullable(); // Campaign-level credential overrides

            // Job management
            $table->unsignedInteger('max_concurrent_jobs')->default(10);
            $table->unsignedInteger('retention_days')->default(90);

            // Publishing
            $table->timestamp('published_at')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('slug');
            $table->index('state');
            $table->index('type');
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
