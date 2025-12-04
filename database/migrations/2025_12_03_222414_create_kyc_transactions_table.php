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
        Schema::create('kyc_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique()->comment('HyperVerge transaction ID (e.g., EKYC-1234567890-1234)');
            $table->foreignUlid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->ulid('document_id')->comment('Document ULID in tenant database');
            $table->ulid('processor_execution_id')->nullable()->comment('ProcessorExecution ULID in tenant database');
            $table->string('workflow_id', 26)->nullable()->comment('Laravel Workflow ID for signaling');
            $table->string('document_job_id', 26)->nullable()->comment('DocumentJob ULID in tenant database');
            $table->string('status')->default('pending')->comment('HyperVerge status: auto_approved, approved, needs_review, auto_declined, rejected, user_cancelled, error');
            $table->json('metadata')->nullable()->comment('Additional data: redirect_url, contact info, etc.');
            $table->timestamp('callback_received_at')->nullable()->comment('When GET callback redirect was received from browser');
            $table->timestamp('webhook_received_at')->nullable()->comment('When data fetch completed (misnomer, kept for compatibility)');
            $table->timestamps();
            
            // Indexes for fast lookups
            $table->index('tenant_id');
            $table->index('document_id');
            $table->index('workflow_id');
            $table->index('document_job_id');
            $table->index('status');
            $table->index(['tenant_id', 'document_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_transactions');
    }
};
