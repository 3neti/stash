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
        Schema::create('processors', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('class_name');
            $table->enum('category', ['ocr', 'classification', 'extraction', 'validation', 'enrichment', 'notification', 'storage', 'custom']);
            $table->text('description')->nullable();
            $table->json('config_schema')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('version')->default('1.0.0');
            $table->string('author')->nullable();
            $table->string('icon')->nullable();
            $table->string('documentation_url')->nullable();
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processors');
    }
};
