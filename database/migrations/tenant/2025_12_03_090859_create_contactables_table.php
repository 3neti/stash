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
        Schema::connection('tenant')->create('contactables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->ulidMorphs('contactable'); // contactable_id (ULID), contactable_type
            $table->string('relationship_type')->nullable(); // 'signer', 'recipient', etc.
            $table->json('metadata')->nullable(); // Custom data per relationship
            $table->timestamps();
            
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');
            
            $table->unique(
                ['contact_id', 'contactable_id', 'contactable_type'],
                'contactable_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('contactables');
    }
};
