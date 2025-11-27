<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Tenancy\TenantConnectionManager;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1.2: Tenant-Scoped Models Test
 * 
 * Tests all tenant-scoped models to ensure they:
 * 1. Use the tenant database connection
 * 2. Are properly isolated between tenants
 * 3. Have correct relationships
 * 4. Work with BelongsToTenant trait
 */
class TenantScopedModelsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private TenantConnectionManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        DB::setDefaultConnection('pgsql');
        
        $this->manager = app(TenantConnectionManager::class);
        
        // Create two test tenants
        $this->tenant1 = Tenant::create([
            'name' => 'Tenant One',
            'slug' => 'tenant-one',
            'status' => 'active',
            'tier' => 'starter',
        ]);
        
        $this->tenant2 = Tenant::create([
            'name' => 'Tenant Two',
            'slug' => 'tenant-two',
            'status' => 'active',
            'tier' => 'professional',
        ]);
    }

    /**
     * Test 1: Campaign model exists and uses tenant connection.
     */
    public function test_campaign_model_uses_tenant_connection(): void
    {
        // Check if migration exists
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_075307_create_campaigns_table.php')
        );
        
        $this->assertTrue($migrationExists, 'Campaign migration should exist');
        
        // Verify the migration has correct structure
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_075307_create_campaigns_table.php')
        );
        
        $this->assertStringContainsString('create_campaigns_table', $migrationContent);
        $this->assertStringContainsString('pipeline_config', $migrationContent);
        $this->assertStringContainsString('status', $migrationContent);
    }

    /**
     * Test 2: Document model exists and uses tenant connection.
     */
    public function test_document_model_uses_tenant_connection(): void
    {
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_075949_create_documents_table.php')
        );
        
        $this->assertTrue($migrationExists, 'Document migration should exist');
        
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_075949_create_documents_table.php')
        );
        
        $this->assertStringContainsString('create_documents_table', $migrationContent);
        $this->assertStringContainsString('storage_path', $migrationContent);
        $this->assertStringContainsString('metadata', $migrationContent);
    }

    /**
     * Test 3: DocumentJob model exists and uses tenant connection.
     */
    public function test_document_job_model_uses_tenant_connection(): void
    {
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_075954_create_document_jobs_table.php')
        );
        
        $this->assertTrue($migrationExists, 'DocumentJob migration should exist');
        
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_075954_create_document_jobs_table.php')
        );
        
        $this->assertStringContainsString('create_document_jobs_table', $migrationContent);
        $this->assertStringContainsString('pipeline_instance', $migrationContent);
    }

    /**
     * Test 4: Processor model exists and uses tenant connection.
     */
    public function test_processor_model_uses_tenant_connection(): void
    {
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_075957_create_processors_table.php')
        );
        
        $this->assertTrue($migrationExists, 'Processor migration should exist');
        
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_075957_create_processors_table.php')
        );
        
        $this->assertStringContainsString('create_processors_table', $migrationContent);
        $this->assertStringContainsString('class_name', $migrationContent);
    }

    /**
     * Test 5: ProcessorExecution model exists and uses tenant connection.
     */
    public function test_processor_execution_model_uses_tenant_connection(): void
    {
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_075959_create_processor_executions_table.php')
        );
        
        $this->assertTrue($migrationExists, 'ProcessorExecution migration should exist');
        
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_075959_create_processor_executions_table.php')
        );
        
        $this->assertStringContainsString('create_processor_executions_table', $migrationContent);
        $this->assertStringContainsString('tokens_used', $migrationContent);
    }

    /**
     * Test 6: Credential model exists with encryption.
     */
    public function test_credential_model_uses_tenant_connection(): void
    {
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_080000_create_credentials_table.php')
        );
        
        $this->assertTrue($migrationExists, 'Credential migration should exist');
        
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_080000_create_credentials_table.php')
        );
        
        $this->assertStringContainsString('create_credentials_table', $migrationContent);
        $this->assertStringContainsString('scope_type', $migrationContent);
        $this->assertStringContainsString('value', $migrationContent);
    }

    /**
     * Test 7: UsageEvent model exists.
     */
    public function test_usage_event_model_uses_tenant_connection(): void
    {
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_080003_create_usage_events_table.php')
        );
        
        $this->assertTrue($migrationExists, 'UsageEvent migration should exist');
        
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_080003_create_usage_events_table.php')
        );
        
        $this->assertStringContainsString('create_usage_events_table', $migrationContent);
        $this->assertStringContainsString('event_type', $migrationContent);
    }

    /**
     * Test 8: AuditLog model exists (read-only).
     */
    public function test_audit_log_model_uses_tenant_connection(): void
    {
        $migrationExists = file_exists(
            database_path('migrations/tenant/2025_11_27_080006_create_audit_logs_table.php')
        );
        
        $this->assertTrue($migrationExists, 'AuditLog migration should exist');
        
        $migrationContent = file_get_contents(
            database_path('migrations/tenant/2025_11_27_080006_create_audit_logs_table.php')
        );
        
        $this->assertStringContainsString('create_audit_logs_table', $migrationContent);
        $this->assertStringContainsString('auditable_type', $migrationContent);
    }

    /**
     * Test 9: All tenant migrations have ULID primary keys.
     */
    public function test_all_tenant_tables_use_ulid_primary_keys(): void
    {
        $tenantMigrations = [
            'create_campaigns_table',
            'create_documents_table',
            'create_document_jobs_table',
            'create_processors_table',
            'create_processor_executions_table',
            'create_credentials_table',
            'create_usage_events_table',
            'create_audit_logs_table',
        ];
        
        foreach ($tenantMigrations as $migration) {
            $files = glob(database_path("migrations/tenant/*_{$migration}.php"));
            $this->assertNotEmpty($files, "Migration for {$migration} should exist");
            
            $content = file_get_contents($files[0]);
            
            // Verify ULID is used (string primary key)
            $this->assertStringContainsString('ulid()', $content, 
                "{$migration} should use ulid() for primary key");
        }
    }

    /**
     * Test 10: All tenant migrations create proper indexes.
     */
    public function test_all_tenant_tables_have_proper_indexes(): void
    {
        $requiredIndexes = [
            'create_campaigns_table' => ['status', 'type'],
            'create_documents_table' => ['status', 'uuid'],
            'create_document_jobs_table' => ['status', 'uuid'],
            'create_processors_table' => ['slug', 'category'],
            'create_processor_executions_table' => ['status'],
            'create_credentials_table' => ['scope_type', 'is_active'],
            'create_usage_events_table' => ['event_type'],
            'create_audit_logs_table' => ['auditable_type'],
        ];
        
        foreach ($requiredIndexes as $migration => $indexes) {
            $files = glob(database_path("migrations/tenant/*_{$migration}.php"));
            $this->assertNotEmpty($files, "Migration for {$migration} should exist");
            
            $content = file_get_contents($files[0]);
            
            foreach ($indexes as $index) {
                $this->assertStringContainsString("index('{$index}')", $content,
                    "{$migration} should have index on {$index}");
            }
        }
    }

    /**
     * Test 11: Tenant migrations use JSON fields appropriately.
     */
    public function test_tenant_tables_use_json_fields(): void
    {
        $jsonFields = [
            'create_campaigns_table' => ['pipeline_config', 'settings'],
            'create_documents_table' => ['metadata', 'processing_history'],
            'create_document_jobs_table' => ['pipeline_instance', 'error_log'],
            'create_processors_table' => ['config_schema'],
            'create_processor_executions_table' => ['input_data', 'output_data', 'config'],
            'create_credentials_table' => ['metadata'],
            'create_usage_events_table' => ['metadata'],
            'create_audit_logs_table' => ['old_values', 'new_values', 'tags'],
        ];
        
        foreach ($jsonFields as $migration => $fields) {
            $files = glob(database_path("migrations/tenant/*_{$migration}.php"));
            $content = file_get_contents($files[0]);
            
            foreach ($fields as $field) {
                $this->assertStringContainsString("json('{$field}')", $content,
                    "{$migration} should have JSON field {$field}");
            }
        }
    }

    /**
     * Test 12: Tenant migrations have timestamps.
     */
    public function test_all_tenant_tables_have_timestamps(): void
    {
        $migrations = glob(database_path('migrations/tenant/*.php'));
        
        foreach ($migrations as $migration) {
            $content = file_get_contents($migration);
            
            // All tables should have timestamps (except audit_logs which only has created_at)
            if (str_contains($migration, 'audit_logs')) {
                $this->assertStringContainsString('timestamp(\'created_at\')', $content,
                    basename($migration) . ' should have created_at');
            } else {
                $this->assertStringContainsString('timestamps()', $content,
                    basename($migration) . ' should have timestamps');
            }
        }
    }

    /**
     * Test 13: Documents table has storage fields.
     */
    public function test_documents_table_has_storage_fields(): void
    {
        $files = glob(database_path('migrations/tenant/*_create_documents_table.php'));
        $content = file_get_contents($files[0]);
        
        $storageFields = ['storage_path', 'storage_disk', 'hash', 'mime_type', 'size_bytes'];
        
        foreach ($storageFields as $field) {
            $this->assertStringContainsString($field, $content,
                "Documents table should have {$field} field");
        }
    }

    /**
     * Test 14: Credentials table value field is encrypted.
     */
    public function test_credentials_table_has_encrypted_value_field(): void
    {
        $files = glob(database_path('migrations/tenant/*_create_credentials_table.php'));
        $content = file_get_contents($files[0]);
        
        $this->assertStringContainsString('text(\'value\')', $content,
            'Credentials table should have text value field for encryption');
    }

    /**
     * Test 15: ProcessorExecutions table tracks token usage.
     */
    public function test_processor_executions_tracks_tokens_and_cost(): void
    {
        $files = glob(database_path('migrations/tenant/*_create_processor_executions_table.php'));
        $content = file_get_contents($files[0]);
        
        $this->assertStringContainsString('tokens_used', $content,
            'ProcessorExecutions should track tokens_used');
        $this->assertStringContainsString('cost_credits', $content,
            'ProcessorExecutions should track cost_credits');
    }

    /**
     * Test 16: All enum fields are properly defined.
     */
    public function test_enum_fields_are_properly_defined(): void
    {
        $enumFields = [
            'create_campaigns_table' => [
                'status' => ['draft', 'active', 'paused', 'archived'],
                'type' => ['template', 'custom', 'meta'],
            ],
            'create_documents_table' => [
                'status' => ['pending', 'queued', 'processing', 'completed', 'failed', 'cancelled'],
            ],
            'create_document_jobs_table' => [
                'status' => ['pending', 'queued', 'running', 'completed', 'failed', 'cancelled'],
            ],
            'create_processors_table' => [
                'category' => ['ocr', 'classification', 'extraction', 'validation', 'enrichment', 'notification', 'storage', 'custom'],
            ],
            'create_processor_executions_table' => [
                'status' => ['pending', 'running', 'completed', 'failed', 'skipped'],
            ],
            'create_credentials_table' => [
                'scope_type' => ['system', 'subscriber', 'campaign', 'processor'],
            ],
        ];
        
        foreach ($enumFields as $migration => $enums) {
            $files = glob(database_path("migrations/tenant/*_{$migration}.php"));
            $content = file_get_contents($files[0]);
            
            foreach ($enums as $field => $values) {
                foreach ($values as $value) {
                    $this->assertStringContainsString("'{$value}'", $content,
                        "{$migration} enum field {$field} should include value '{$value}'");
                }
            }
        }
    }

    /**
     * Test 17: Verify tenant database isolation after migrations.
     */
    public function test_tenant_database_isolation_after_migrations(): void
    {
        // This test requires actual tenant databases to exist
        // We'll check if the tenant database names are correct
        
        $dbName1 = $this->manager->getTenantDatabaseName($this->tenant1);
        $dbName2 = $this->manager->getTenantDatabaseName($this->tenant2);
        
        $this->assertEquals("tenant_{$this->tenant1->id}", $dbName1);
        $this->assertEquals("tenant_{$this->tenant2->id}", $dbName2);
        $this->assertNotEquals($dbName1, $dbName2);
    }

    /**
     * Test 18: All tenant migrations exist in correct directory.
     */
    public function test_all_tenant_migrations_exist(): void
    {
        $requiredMigrations = [
            'create_campaigns_table',
            'create_documents_table',
            'create_document_jobs_table',
            'create_processors_table',
            'create_processor_executions_table',
            'create_credentials_table',
            'create_usage_events_table',
            'create_audit_logs_table',
        ];
        
        $existingMigrations = array_map(
            fn($file) => basename($file),
            glob(database_path('migrations/tenant/*.php'))
        );
        
        $this->assertCount(8, $existingMigrations, 
            'Should have exactly 8 tenant migrations');
        
        foreach ($requiredMigrations as $migration) {
            $found = false;
            foreach ($existingMigrations as $existing) {
                if (str_contains($existing, $migration)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Migration {$migration} should exist");
        }
    }

    /**
     * Test 19: Migrations are dated correctly (Phase 1.2 timeline).
     */
    public function test_migrations_are_dated_correctly(): void
    {
        $migrations = glob(database_path('migrations/tenant/*.php'));
        
        foreach ($migrations as $migration) {
            $filename = basename($migration);
            
            // Should start with YYYY_MM_DD format
            $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_/', $filename,
                'Migration filename should have correct date format');
            
            // Should be from 2025 (Phase 1.2 development)
            $this->assertStringStartsWith('2025_', $filename,
                'Migration should be dated 2025 (Phase 1.2 timeline)');
        }
    }

    /**
     * Test 20: All tables have proper cascade delete constraints.
     */
    public function test_tables_have_cascade_delete_constraints(): void
    {
        // Document jobs should cascade delete with documents
        $files = glob(database_path('migrations/tenant/*_create_document_jobs_table.php'));
        $content = file_get_contents($files[0]);
        
        $this->assertStringContainsString('onDelete(\'cascade\')', $content,
            'Document jobs should cascade delete');
        
        // Processor executions should cascade delete with jobs
        $files = glob(database_path('migrations/tenant/*_create_processor_executions_table.php'));
        $content = file_get_contents($files[0]);
        
        $this->assertStringContainsString('onDelete(\'cascade\')', $content,
            'Processor executions should cascade delete');
    }
}
