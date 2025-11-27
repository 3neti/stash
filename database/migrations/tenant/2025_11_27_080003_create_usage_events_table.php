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
        Schema::create('usage_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id')->nullable();
            $table->ulid('document_id')->nullable();
            $table->ulid('job_id')->nullable();
            $table->enum('event_type', ['upload', 'storage', 'processor_execution', 'ai_task', 'connector_call', 'agent_tool']);
            $table->integer('units');
            $table->integer('cost_credits');
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('job_id')->references('id')->on('document_jobs')->cascadeOnDelete();

            $table->index(['campaign_id', 'event_type', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_events');
    }
};
