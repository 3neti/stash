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
        Schema::table('processors', function (Blueprint $table) {
            if (!Schema::hasColumn('processors', 'output_schema')) {
                $table->json('output_schema')->nullable()->after('config_schema');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('processors', function (Blueprint $table) {
            if (Schema::hasColumn('processors', 'output_schema')) {
                $table->dropColumn('output_schema');
            }
        });
    }
};
