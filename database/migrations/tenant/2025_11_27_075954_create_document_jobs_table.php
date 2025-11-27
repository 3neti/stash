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
        Schema::create('document_jobs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->ulid('campaign_id');
            $table->ulid('document_id');
            $table->json('pipeline_instance');
            $table->integer('current_processor_index')->default(0);
            $table->enum('status', ['pending', 'queued', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('queue_name')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->json('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();

            $table->index(['campaign_id', 'status']);
            $table->index('document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_jobs');
    }
};
