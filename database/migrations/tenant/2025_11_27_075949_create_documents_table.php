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
        Schema::create('documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->uuid('uuid')->unique();
            $table->ulid('campaign_id');
            $table->ulid('user_id')->nullable();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->bigInteger('size_bytes');
            $table->string('storage_path');
            $table->string('storage_disk')->default('s3');
            $table->string('hash', 64);
            $table->string('state')->default('pending');
            $table->json('metadata')->nullable();
            $table->json('processing_history')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            // Note: user_id references central database users, so no foreign key constraint

            $table->index(['campaign_id', 'state']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
