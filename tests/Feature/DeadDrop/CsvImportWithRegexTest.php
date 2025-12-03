<?php

namespace Tests\Feature\DeadDrop;

use Tests\DeadDropTestCase;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\Processor;
use App\Models\Tenant;
use App\Services\Processors\PortPHP\CsvImportProcessor;
use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use Illuminate\Support\Facades\Storage;
use App\Tenancy\TenantContext;

class CsvImportWithRegexTest extends DeadDropTestCase
{
    private Campaign $campaign;
    private CsvImportProcessor $processor;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->processor = new CsvImportProcessor();
        
        // Create tenant for testing
        $this->tenant = Tenant::on('central')->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        // Initialize tenant context
        TenantContext::run($this->tenant, function () {
            // Create processor for CSV import
            $processor = Processor::create([
                'name' => 'CSV Importer Test',
                'slug' => 'csv-importer-test',
                'class_name' => \App\Services\Processors\PortPHP\CsvImportProcessor::class,
                'category' => 'ocr',
                'is_active' => true,
            ]);
            
            // Create test campaign with regex transformations
            $this->campaign = Campaign::create([
                'name' => 'Customer Data Import with Regex Extraction',
                'slug' => 'customer-data-regex-import',
                'description' => 'Test campaign for regex transformations',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processor->id, 'type' => 'ocr', 'config' => [
                            'delimiter' => ',',
                            'has_headers' => true,
                            'date_columns' => ['registration_date'],
                            'date_format' => 'Y-m-d',
                            
                            'transformations' => [
                                'regex_transformations' => [
                                    // Transform existing fields in-place
                                    'phone' => [
                                        'type' => 'extract',
                                        'pattern' => '/^(\+63|0)?(9\d{2})\d{7}$/',
                                        'group' => 2,  // Extract area code: +639171234567 → 917
                                    ],
                                    'registration_date' => [
                                        'type' => 'replace',
                                        'pattern' => '/^(\d{2})\/(\d{2})\/(\d{4})$/',
                                        'replacement' => '$3-$1-$2',  // MM/DD/YYYY → YYYY-MM-DD
                                    ],
                                    'email' => [
                                        'type' => 'extract',
                                        'pattern' => '/@(.+)$/',
                                        'group' => 1,  // Extract domain: john@company.com → company.com
                                    ],
                                    'full_name' => [
                                        'type' => 'split',
                                        'pattern' => '/\s+/',
                                        'output_fields' => ['first_name', 'last_name'],
                                        'remove_original' => false,
                                    ],
                                    'bio' => [
                                        'type' => 'extract_all',
                                        'pattern' => '/#(\w+)/',
                                        'group' => 1,
                                        'output' => 'comma_separated',  // Extract hashtags
                                    ],
                                    'employee_id' => [
                                        'type' => 'replace',
                                        'pattern' => '/^EMP-/',
                                        'replacement' => '',  // Remove prefix: EMP-001234 → 001234
                                    ],
                                ],
                                'uppercase' => ['department'],
                                'trim' => ['email', 'bio'],
                                'integer' => ['age'],
                            ],
                            
                            'filters' => [
                                'validation_rules' => [
                                    // Validate transformed fields
                                    'email' => ['required', 'string'],  // Will be domain only after transform
                                    'phone' => ['required', 'string', 'size:3'],  // Will be area code only
                                    'full_name' => ['required', 'string'],
                                    'first_name' => ['required', 'string'],  // Created by split
                                    'last_name' => ['required', 'string'],   // Created by split
                                    'employee_id' => ['required', 'string'],
                                    'department' => ['required', 'string'],
                                    'age' => ['required', 'integer', 'min:18', 'max:100'],
                                    'registration_date' => ['required', 'date_format:Y-m-d'],
                                    'bio' => ['required', 'string'],
                                ],
                            ],
                            
                            'export_json' => true,
                        ]],
                    ],
                ],
                'allowed_mime_types' => ['text/csv', 'text/plain', 'application/csv'],
                'max_file_size_bytes' => 10485760,
                'published_at' => now(),
            ]);
        });
    }

    /** @test */
    public function test_csv_import_with_regex_extract_phone_area_code(): void
    {
        // Create test CSV with Philippine phone numbers
        $csvContent = "full_name,email,phone,employee_id,department,age,registration_date,bio\n";
        $csvContent .= "Juan Cruz,juan@test.com,+639171234567,EMP-001234,Engineering,32,12/25/2024,Developer #php\n";
        
        $document = $this->createTestDocument($csvContent);
        
        // Get processor config from campaign
        $processorData = $this->campaign->pipeline_config['processors'][0];
        $config = new ProcessorConfigData(
            id: $processorData['id'],
            type: $processorData['type'],
            config: $processorData['config']
        );
        $context = new ProcessorContextData(
            documentJobId: 'test-job-id',
            processorIndex: 0
        );
        
        // Process document within tenant context
        $result = TenantContext::run($this->tenant, fn() => 
            $this->processor->handle($document, $config, $context)
        );
        
        // Assertions
        $this->assertTrue($result->success, 'Processing should succeed');
        $this->assertNotEmpty($result->output['data'], 'Should have imported data');
        
        $row = $result->output['data'][0];
        
        // Check regex extraction worked: +639171234567 → '917'
        $this->assertEquals('917', $row['phone'], 'Should extract area code from phone (replaces original)');
    }

    /** @test */
    public function test_csv_import_with_regex_replace_date_format(): void
    {
        $csvContent = "full_name,email,phone,employee_id,department,age,registration_date,bio\n";
        $csvContent .= "Maria Santos,maria@test.com,09181234567,EMP-002345,Marketing,28,01/15/2024,Marketer #seo\n";
        
        $document = $this->createTestDocument($csvContent);
        
        $processorData = $this->campaign->pipeline_config['processors'][0];
        $config = new ProcessorConfigData(
            id: $processorData['id'],
            type: $processorData['type'],
            config: $processorData['config']
        );
        $context = new ProcessorContextData(documentJobId: 'test-job-id', processorIndex: 0);
        
        $result = TenantContext::run($this->tenant, fn() => $this->processor->handle($document, $config, $context));
        
        $this->assertTrue($result->success);
        $row = $result->output['data'][0];
        
        // Check date reformatting: 01/15/2024 → 2024-01-15
        $this->assertEquals('2024-01-15', $row['registration_date'], 'Should reformat date from MM/DD/YYYY to YYYY-MM-DD');
    }

    /** @test */
    public function test_csv_import_with_regex_extract_email_domain(): void
    {
        $csvContent = "full_name,email,phone,employee_id,department,age,registration_date,bio\n";
        $csvContent .= "Pedro Reyes,pedro@company.com,9191234567,EMP-003456,Sales,45,06/10/2023,Salesman #b2b\n";
        
        $document = $this->createTestDocument($csvContent);
        
        $processorData = $this->campaign->pipeline_config['processors'][0];
        $config = new ProcessorConfigData(
            id: $processorData['id'],
            type: $processorData['type'],
            config: $processorData['config']
        );
        $context = new ProcessorContextData(documentJobId: 'test-job-id', processorIndex: 0);
        
        $result = TenantContext::run($this->tenant, fn() => $this->processor->handle($document, $config, $context));
        
        $this->assertTrue($result->success);
        $row = $result->output['data'][0];
        
        // Check email domain extraction: pedro@company.com → 'company.com'
        $this->assertEquals('company.com', $row['email'], 'Should extract domain from email (replaces original)');
    }

    /** @test */
    public function test_csv_import_with_regex_split_full_name(): void
    {
        $csvContent = "full_name,email,phone,employee_id,department,age,registration_date,bio\n";
        $csvContent .= "Anna Garcia,anna@test.com,+639201234567,EMP-004567,HR,35,03/20/2024,HR Manager #recruitment\n";
        
        $document = $this->createTestDocument($csvContent);
        
        $processorData = $this->campaign->pipeline_config['processors'][0];
        $config = new ProcessorConfigData(
            id: $processorData['id'],
            type: $processorData['type'],
            config: $processorData['config']
        );
        $context = new ProcessorContextData(documentJobId: 'test-job-id', processorIndex: 0);
        
        $result = TenantContext::run($this->tenant, fn() => $this->processor->handle($document, $config, $context));
        
        $this->assertTrue($result->success);
        $row = $result->output['data'][0];
        
        // Check name splitting: 'Anna Garcia' → first_name='Anna', last_name='Garcia'
        $this->assertEquals('Anna', $row['first_name'], 'Should extract first name');
        $this->assertEquals('Garcia', $row['last_name'], 'Should extract last name');
        
        // Original field should still exist (remove_original = false)
        $this->assertArrayHasKey('full_name', $row, 'Original full_name field should remain');
    }

    /** @test */
    public function test_csv_import_with_regex_extract_all_hashtags(): void
    {
        $csvContent = "full_name,email,phone,employee_id,department,age,registration_date,bio\n";
        $csvContent .= "Jose Tan,jose@test.com,09211234567,EMP-005678,Engineering,29,11/05/2024,\"Engineer #python #api #microservices\"\n";
        
        $document = $this->createTestDocument($csvContent);
        
        $processorData = $this->campaign->pipeline_config['processors'][0];
        $config = new ProcessorConfigData(
            id: $processorData['id'],
            type: $processorData['type'],
            config: $processorData['config']
        );
        $context = new ProcessorContextData(documentJobId: 'test-job-id', processorIndex: 0);
        
        $result = TenantContext::run($this->tenant, fn() => $this->processor->handle($document, $config, $context));
        
        $this->assertTrue($result->success);
        $row = $result->output['data'][0];
        
        // Check hashtag extraction: '#python #api #microservices' → 'python,api,microservices'
        $this->assertEquals('python,api,microservices', $row['bio'], 'Should extract all hashtags as comma-separated');
    }

    /** @test */
    public function test_csv_import_with_regex_replace_remove_prefix(): void
    {
        $csvContent = "full_name,email,phone,employee_id,department,age,registration_date,bio\n";
        $csvContent .= "Test User,test@test.com,09171234567,EMP-999999,IT,30,01/01/2024,Tester #qa\n";
        
        $document = $this->createTestDocument($csvContent);
        
        $processorData = $this->campaign->pipeline_config['processors'][0];
        $config = new ProcessorConfigData(
            id: $processorData['id'],
            type: $processorData['type'],
            config: $processorData['config']
        );
        $context = new ProcessorContextData(documentJobId: 'test-job-id', processorIndex: 0);
        
        $result = TenantContext::run($this->tenant, fn() => $this->processor->handle($document, $config, $context));
        
        $this->assertTrue($result->success);
        $row = $result->output['data'][0];
        
        // Check employee ID prefix removal: 'EMP-999999' → '999999'
        $this->assertEquals('999999', $row['employee_id'], 'Should remove EMP- prefix');
    }

    /** @test */
    public function test_csv_import_with_all_regex_transformations_combined(): void
    {
        // Test all transformations together with the actual test CSV
        $csvContent = file_get_contents('/tmp/customers_regex_test.csv');
        
        $document = $this->createTestDocument($csvContent);
        
        $processorData = $this->campaign->pipeline_config['processors'][0];
        $config = new ProcessorConfigData(
            id: $processorData['id'],
            type: $processorData['type'],
            config: $processorData['config']
        );
        $context = new ProcessorContextData(documentJobId: 'test-job-id', processorIndex: 0);
        
        $result = TenantContext::run($this->tenant, fn() => $this->processor->handle($document, $config, $context));
        
        // Assertions
        $this->assertTrue($result->success, 'Processing should succeed');
        $this->assertCount(5, $result->output['data'], 'Should import all 5 rows');
        
        // Check first row (Juan Dela Cruz)
        $juan = $result->output['data'][0];
        $this->assertEquals('917', $juan['phone'], 'Phone area code extracted (replaces original)');
        $this->assertEquals('2024-12-25', $juan['registration_date'], 'Date reformatted');
        $this->assertEquals('company.com', $juan['email'], 'Email domain extracted (replaces original)');
        $this->assertEquals('Juan', $juan['first_name'], 'First name extracted');
        $this->assertStringContainsString('Dela', $juan['last_name'], 'Last name extracted');
        $this->assertEquals('php,laravel,vue', $juan['bio'], 'Hashtags extracted');
        $this->assertEquals('001234', $juan['employee_id'], 'Employee ID prefix removed');
        $this->assertEquals('ENGINEERING', $juan['department'], 'Department uppercased (simple transformation)');
        
        // Check second row (Maria Santos)
        $maria = $result->output['data'][1];
        $this->assertEquals('918', $maria['phone'], 'Area code extracted');
        $this->assertEquals('2024-01-15', $maria['registration_date'], 'Date reformatted');
        $this->assertEquals('Maria', $maria['first_name'], 'First name extracted');
        $this->assertEquals('Santos', $maria['last_name'], 'Last name extracted');
        
        // Check third row (Pedro Reyes)
        $pedro = $result->output['data'][2];
        $this->assertEquals('919', $pedro['phone'], 'Area code extracted');
        $this->assertEquals('2023-06-10', $pedro['registration_date'], 'Date reformatted');
        
        // Verify metadata
        $this->assertEquals(5, $result->output['rows_imported'], 'Should report 5 rows imported');
        $this->assertEquals(0, $result->output['rows_failed'], 'Should have no failed rows');
    }

    /**
     * Helper method to create a test document with CSV content.
     */
    private function createTestDocument(string $csvContent): Document
    {
        // Create temporary CSV file
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($tempFile, $csvContent);
        
        // Store in Storage disk
        $filename = 'test_' . uniqid() . '.csv';
        Storage::disk('local')->put($filename, file_get_contents($tempFile));
        
        // Create document record
        $fileSize = filesize($tempFile);
        $fileHash = hash_file('sha256', $tempFile);
        $document = Document::create([
            'campaign_id' => $this->campaign->id,
            'original_filename' => $filename,
            'storage_path' => $filename,
            'storage_disk' => 'local',
            'mime_type' => 'text/csv',
            'size_bytes' => $fileSize,
            'hash' => $fileHash,
            'state' => \App\States\Document\PendingDocumentState::class,
        ]);
        
        // Clean up temp file
        unlink($tempFile);
        
        return $document;
    }

    protected function tearDown(): void
    {
        // Clean up any test files
        $files = Storage::disk('local')->files();
        foreach ($files as $file) {
            if (str_starts_with($file, 'test_')) {
                Storage::disk('local')->delete($file);
            }
        }
        
        parent::tearDown();
    }
}
