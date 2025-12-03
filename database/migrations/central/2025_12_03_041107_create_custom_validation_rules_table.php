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
        Schema::create('custom_validation_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name'); // e.g., "valid_phone_ph"
            $table->string('label'); // e.g., "Valid Philippine Phone Number"
            $table->text('description')->nullable();
            $table->string('type')->default('regex'); // 'regex', 'expression', 'callback' (future)
            $table->json('config'); // Pattern, message, and other type-specific config
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_validation_rules');
    }
};
