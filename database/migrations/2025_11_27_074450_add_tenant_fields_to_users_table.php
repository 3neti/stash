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
        Schema::table('users', function (Blueprint $table) {
            // Add tenant_id for multi-tenancy (stancl/tenancy uses 'tenant_id' column)
            $table->string('tenant_id')->after('id')->nullable();
            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();
            
            // Add role and permissions for RBAC
            $table->enum('role', ['owner', 'admin', 'member', 'viewer'])
                ->default('member')
                ->after('password');
            $table->json('permissions')->nullable()->after('role');
            
            // Add indexes
            $table->index('tenant_id');
            $table->index('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['tenant_id', 'role', 'permissions']);
        });
    }
};
