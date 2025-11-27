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
        Schema::create('processor_executions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('job_id');
            $table->ulid('processor_id');
            $table->json('input_data');
            $table->json('output_data')->nullable();
            $table->json('config');
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'skipped'])->default('pending');
            $table->integer('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->integer('cost_credits')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('document_jobs')->cascadeOnDelete();
            $table->foreign('processor_id')->references('id')->on('processors')->cascadeOnDelete();

            $table->index(['job_id', 'status']);
            $table->index('processor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processor_executions');
    }
};
