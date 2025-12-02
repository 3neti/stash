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
        Schema::create('pipeline_progress', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('job_id')->unique();
            $table->integer('stage_count')->default(0);
            $table->integer('completed_stages')->default(0);
            $table->float('percentage_complete')->default(0);
            $table->string('current_stage')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('document_jobs')->cascadeOnDelete();
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_progress');
    }
};
