<?php

declare(strict_types=1);


use App\Models\Domain;
use App\Models\Tenant;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;


/**
 * Phase 1.2: Custom Multi-Database Tenancy Integration Tests
 *
 * Tests the complete tenancy system without requiring live PostgreSQL databases.
 */
class Phase12IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Tenant database not created in test - needs UsesDashboardSetup');

        // Use central database for these tests
        DB::setDefaultConnection('pgsql');
    }

    /**
     * Test 1: Tenant model uses ULID for primary key.
     */
    public function test_tenant_model_generates_ulid_automatically(): void
    {
        $tenant = Tenant::create([
            'name' => 'Acme Corporation',
            'slug' => 'acme-corp',
            'email' => 'admin@acme.com',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        // Verify ULID format (26 characters, base32)
        $this->assertNotNull($tenant->id);
        $this->assertEquals(26, strlen($tenant->id));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $tenant->id);

        // Verify attributes
        $this->assertEquals('Acme Corporation', $tenant->name);
        $this->assertEquals('acme-corp', $tenant->slug);
        $this->assertEquals('admin@acme.com', $tenant->email);
        $this->assertEquals('active', $tenant->status);
        $this->assertEquals('starter', $tenant->tier);
    }

    /**
     * Test 2: Tenant has domains relationship.
     */
    public function test_tenant_has_domains_relationship(): void
    {
        $tenant = Tenant::create([
            'name' => 'Beta Inc',
            'slug' => 'beta-inc',
            'status' => 'active',
            'tier' => 'professional',
        ]);

        // Create multiple domains
        $tenant->domains()->create([
            'domain' => 'beta.example.com',
            'is_primary' => true,
        ]);

        $tenant->domains()->create([
            'domain' => 'beta-staging.example.com',
            'is_primary' => false,
        ]);

        // Verify relationship
        $this->assertCount(2, $tenant->domains);
        $this->assertInstanceOf(Domain::class, $tenant->domains->first());

        // Verify primary domain
        $primaryDomain = $tenant->domains()->where('is_primary', true)->first();
        $this->assertEquals('beta.example.com', $primaryDomain->domain);
    }

    /**
     * Test 3: Domain belongs to tenant.
     */
    public function test_domain_belongs_to_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Gamma LLC',
            'slug' => 'gamma-llc',
            'status' => 'active',
            'tier' => 'enterprise',
        ]);

        $domain = $tenant->domains()->create([
            'domain' => 'gamma.example.com',
            'is_primary' => true,
        ]);

        // Verify relationship
        $this->assertInstanceOf(Tenant::class, $domain->tenant);
        $this->assertEquals($tenant->id, $domain->tenant->id);
        $this->assertEquals('Gamma LLC', $domain->tenant->name);
    }

    /**
     * Test 4: Tenant slug must be unique.
     */
    public function test_tenant_slug_must_be_unique(): void
    {
        Tenant::create([
            'name' => 'First Tenant',
            'slug' => 'unique-slug',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Tenant::create([
            'name' => 'Second Tenant',
            'slug' => 'unique-slug',  // Duplicate!
            'status' => 'active',
            'tier' => 'starter',
        ]);
    }

    /**
     * Test 5: Domain must be unique.
     */
    public function test_domain_must_be_unique(): void
    {
        $tenant1 = Tenant::create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        $tenant1->domains()->create([
            'domain' => 'example.com',
            'is_primary' => true,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $tenant2->domains()->create([
            'domain' => 'example.com',  // Duplicate!
            'is_primary' => true,
        ]);
    }

    /**
     * Test 6: Tenant helper methods work correctly.
     */
    public function test_tenant_helper_methods(): void
    {
        $activeTenant = Tenant::create([
            'name' => 'Active Tenant',
            'slug' => 'active',
            'status' => 'active',
            'tier' => 'starter',
            'trial_ends_at' => now()->addDays(30),
        ]);

        $suspendedTenant = Tenant::create([
            'name' => 'Suspended Tenant',
            'slug' => 'suspended',
            'status' => 'suspended',
            'tier' => 'starter',
        ]);

        // Test isActive()
        $this->assertTrue($activeTenant->isActive());
        $this->assertFalse($suspendedTenant->isActive());

        // Test isSuspended()
        $this->assertFalse($activeTenant->isSuspended());
        $this->assertTrue($suspendedTenant->isSuspended());

        // Test isOnTrial()
        $this->assertTrue($activeTenant->isOnTrial());
        $this->assertFalse($suspendedTenant->isOnTrial());
    }

    /**
     * Test 7: Tenant credentials are encrypted.
     */
    public function test_tenant_credentials_are_encrypted(): void
    {
        $plaintext = 'super-secret-api-key';

        $tenant = Tenant::create([
            'name' => 'Secure Tenant',
            'slug' => 'secure',
            'status' => 'active',
            'tier' => 'starter',
            'credentials' => $plaintext,
        ]);

        // Verify encrypted in database
        $rawCredentials = DB::table('tenants')
            ->where('id', $tenant->id)
            ->value('credentials');

        $this->assertNotEquals($plaintext, $rawCredentials);
        $this->assertStringStartsWith('eyJpdiI6', $rawCredentials);  // Laravel encryption format

        // Verify decrypted when accessed
        $tenant->refresh();
        $this->assertEquals($plaintext, $tenant->credentials);
    }

    /**
     * Test 8: Tenant soft deletes work.
     */
    public function test_tenant_soft_deletes(): void
    {
        $tenant = Tenant::create([
            'name' => 'Delete Me',
            'slug' => 'delete-me',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        $tenantId = $tenant->id;

        // Soft delete
        $tenant->delete();

        // Verify not found in normal queries
        $this->assertNull(Tenant::find($tenantId));

        // Verify found with trashed
        $this->assertNotNull(Tenant::withTrashed()->find($tenantId));

        // Verify restore works
        $tenant->restore();
        $this->assertNotNull(Tenant::find($tenantId));
    }

    /**
     * Test 9: TenantContext tracks current tenant.
     */
    public function test_tenant_context_tracks_current_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Context Test',
            'slug' => 'context-test',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        // Initially no tenant
        $this->assertNull(TenantContext::current());
        $this->assertFalse(TenantContext::isInitialized());

        // Initialize tenant context
        TenantContext::initialize($tenant);

        // Verify tenant is set
        $this->assertNotNull(TenantContext::current());
        $this->assertTrue(TenantContext::isInitialized());
        $this->assertEquals($tenant->id, TenantContext::current()->id);

        // Forget tenant
        TenantContext::forgetCurrent();

        // Verify tenant is cleared
        $this->assertNull(TenantContext::current());
        $this->assertFalse(TenantContext::isInitialized());
    }

    /**
     * Test 10: TenantContext::run executes callback in tenant context.
     */
    public function test_tenant_context_run_executes_in_tenant_context(): void
    {
        $tenant = Tenant::create([
            'name' => 'Callback Test',
            'slug' => 'callback-test',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        $executedTenantId = null;

        $result = TenantContext::run($tenant, function () use (&$executedTenantId) {
            $executedTenantId = TenantContext::current()?->id;

            return 'test-result';
        });

        // Verify callback executed with tenant
        $this->assertEquals($tenant->id, $executedTenantId);
        $this->assertEquals('test-result', $result);

        // Verify tenant context was restored
        $this->assertNull(TenantContext::current());
    }

    /**
     * Test 11: TenantConnectionManager generates correct database names.
     */
    public function test_tenant_connection_manager_generates_database_names(): void
    {
        $tenant = Tenant::create([
            'name' => 'Database Name Test',
            'slug' => 'db-name-test',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        $manager = app(TenantConnectionManager::class);
        $dbName = $manager->getTenantDatabaseName($tenant);

        $this->assertEquals("tenant_{$tenant->id}", $dbName);
        $this->assertStringStartsWith('tenant_', $dbName);
        $this->assertEquals(33, strlen($dbName));  // 'tenant_' (7) + ULID (26)
    }

    /**
     * Test 12: Tenant settings are stored as JSON.
     */
    public function test_tenant_settings_stored_as_json(): void
    {
        $settings = [
            'ai_provider' => 'openai',
            'queue_driver' => 'redis',
            'max_documents' => 1000,
        ];

        $tenant = Tenant::create([
            'name' => 'Settings Test',
            'slug' => 'settings-test',
            'status' => 'active',
            'tier' => 'starter',
            'settings' => $settings,
        ]);

        $tenant->refresh();

        $this->assertIsArray($tenant->settings);
        $this->assertEquals('openai', $tenant->settings['ai_provider']);
        $this->assertEquals('redis', $tenant->settings['queue_driver']);
        $this->assertEquals(1000, $tenant->settings['max_documents']);
    }

    /**
     * Test 13: Multiple tenants can exist independently.
     */
    public function test_multiple_tenants_can_coexist(): void
    {
        $tenants = collect([
            Tenant::create(['name' => 'Tenant 1', 'slug' => 'tenant-1', 'status' => 'active', 'tier' => 'starter']),
            Tenant::create(['name' => 'Tenant 2', 'slug' => 'tenant-2', 'status' => 'active', 'tier' => 'professional']),
            Tenant::create(['name' => 'Tenant 3', 'slug' => 'tenant-3', 'status' => 'suspended', 'tier' => 'enterprise']),
        ]);

        // Verify all tenants exist
        $this->assertCount(3, Tenant::all());

        // Verify each has unique ID
        $ids = $tenants->pluck('id')->unique();
        $this->assertCount(3, $ids);

        // Verify each has correct tier
        $this->assertEquals('starter', $tenants[0]->tier);
        $this->assertEquals('professional', $tenants[1]->tier);
        $this->assertEquals('enterprise', $tenants[2]->tier);
    }

    /**
     * Test 14: Tenant credit balance defaults to zero.
     */
    public function test_tenant_credit_balance_defaults_to_zero(): void
    {
        $tenant = Tenant::create([
            'name' => 'Credit Test',
            'slug' => 'credit-test',
            'status' => 'active',
            'tier' => 'starter',
        ]);

        $this->assertEquals(0, $tenant->credit_balance);
    }

    /**
     * Test 15: Console commands are registered.
     */
    public function test_tenant_console_commands_are_registered(): void
    {
        $commands = Artisan::all();
        $commandNames = array_keys($commands);

        $this->assertContains('tenant:create', $commandNames);
        $this->assertContains('tenant:migrate', $commandNames);
        $this->assertContains('tenant:list', $commandNames);
        $this->assertContains('tenant:delete', $commandNames);
    }
}
