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
        Schema::connection('tenant')->create('contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('mobile')->nullable()->index(); // Nullable for eKYC - may not have phone
            $table->string('country')->default('PH');
            $table->string('bank_account')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->json('meta')->nullable(); // Schemaless attributes
            
            // eKYC operational fields (queryable)
            $table->string('kyc_transaction_id')->nullable()->unique(); // Use as unique identifier
            $table->string('kyc_status')->nullable()->index(); // pending, approved, rejected
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_completed_at')->nullable();
            // Note: kyc_onboarding_url and kyc_rejection_reasons stored in meta JSON field
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contacts');
    }
};
