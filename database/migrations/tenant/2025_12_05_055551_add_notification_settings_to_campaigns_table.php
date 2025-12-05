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
        // Run on tenant connection (campaigns table is in tenant database)
        Schema::connection('tenant')->table('campaigns', function (Blueprint $table) {
            $table->json('notification_settings')->nullable()->after('settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('tenant')->table('campaigns', function (Blueprint $table) {
            $table->dropColumn('notification_settings');
        });
    }
};
