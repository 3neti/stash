<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Processor;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if we should test campaign:import command
        $useImportCommand = env('CAMPAIGN_SEEDER_USE_IMPORT', false);

        // If running in tenant context, seed campaigns
        if (TenantContext::isInitialized()) {
            if ($useImportCommand) {
                $this->seedCampaignsViaImportCommand();
            } else {
                $this->seedCampaigns();
            }
        } else {
            // Otherwise, seed for all tenants
            $tenants = Tenant::on('pgsql')->get();
            foreach ($tenants as $tenant) {
                TenantContext::run($tenant, function () use ($useImportCommand) {
                    if ($useImportCommand) {
                        $this->seedCampaignsViaImportCommand();
                    } else {
                        $this->seedCampaigns();
                    }
                });
            }
        }
    }

    /**
     * Seed campaigns for current tenant context.
     */
    private function seedCampaigns(): void
    {
        $processors = Processor::pluck('id', 'slug')->toArray();

        if (empty($processors)) {
            $this->command->warn('No processors found. Run ProcessorSeeder first.');

            return;
        }

        $campaigns = [
            [
                'name' => 'Invoice Processing Pipeline',
                'slug' => 'invoice-processing',
                'description' => 'Automated pipeline for extracting data from invoices',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['tesseract-ocr'] ?? null, 'type' => 'ocr', 'config' => ['language' => 'eng']],
                        ['id' => $processors['document-classifier'] ?? null, 'type' => 'classification', 'config' => ['categories' => ['invoice']]],
                        ['id' => $processors['data-extractor'] ?? null, 'type' => 'extraction', 'config' => ['entity_types' => ['total', 'date', 'vendor']]],
                        ['id' => $processors['schema-validator'] ?? null, 'type' => 'validation', 'config' => ['strict' => true]],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify vendor name', 'required' => true],
                    ['title' => 'Check invoice total', 'required' => true],
                    ['title' => 'Validate payment terms', 'required' => false],
                ],
                'allowed_mime_types' => [
                    'application/pdf',
                    'image/png',
                    'image/jpeg',
                    'image/tiff',
                ],
                'max_file_size_bytes' => 10485760, // 10MB
                'settings' => [
                    'queue' => 'high-priority',
                    'ai_provider' => 'openai',
                ],
                'max_concurrent_jobs' => 5,
                'retention_days' => 90,
                'published_at' => now(),
            ],
            [
                'name' => 'Receipt OCR Workflow',
                'slug' => 'receipt-ocr',
                'description' => 'Extract text and data from receipts',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['openai-vision-ocr'] ?? null, 'type' => 'ocr', 'config' => ['model' => 'gpt-4-vision-preview']],
                        ['id' => $processors['data-extractor'] ?? null, 'type' => 'extraction', 'config' => ['entity_types' => ['merchant', 'total', 'date', 'items']]],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify merchant name', 'required' => true],
                    ['title' => 'Check total amount', 'required' => true],
                ],
                'allowed_mime_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/heic', // iPhone photos
                ],
                'max_file_size_bytes' => 5242880, // 5MB
                'settings' => [
                    'queue' => 'default',
                    'ai_provider' => 'openai',
                ],
                'max_concurrent_jobs' => 10,
                'retention_days' => 60,
                'published_at' => now(),
            ],
            [
                'name' => 'Contract Analysis',
                'slug' => 'contract-analysis',
                'description' => 'Extract key terms and entities from legal contracts',
                'state' => \App\States\Campaign\DraftCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['tesseract-ocr'] ?? null, 'type' => 'ocr', 'config' => ['language' => 'eng', 'dpi' => 600]],
                        ['id' => $processors['data-extractor'] ?? null, 'type' => 'extraction', 'config' => ['entity_types' => ['parties', 'dates', 'terms', 'amounts']]],
                        ['id' => $processors['data-enricher'] ?? null, 'type' => 'enrichment', 'config' => ['enrichment_sources' => ['legal_db']]],
                    ],
                ],
                'allowed_mime_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
                ],
                'max_file_size_bytes' => 20971520, // 20MB (larger for contracts)
                'settings' => [
                    'queue' => 'low-priority',
                    'ai_provider' => 'anthropic',
                ],
                'max_concurrent_jobs' => 3,
                'retention_days' => 365,
            ],
            [
                'name' => 'Employee CSV Import',
                'slug' => 'employee-csv-import',
                'description' => 'Bulk import employee data with Laravel validation rules',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        // CSV Importer acts as "OCR" step (reads and parses CSV)
                        ['id' => $processors['csv-importer'] ?? null, 'type' => 'ocr', 'config' => [
                            'delimiter' => ',',
                            'has_headers' => true,
                            'date_columns' => ['hire_date'],
                            'date_format' => 'Y-m-d',
                            
                            // Laravel Validation Rules (Advanced Examples)
                            'filters' => [
                                'validation_rules' => [
                                    // Required fields with type validation
                                    'first_name' => ['required', 'string', 'min:2', 'max:100'],
                                    'last_name' => ['required', 'string', 'min:2', 'max:100'],
                                    
                                    // Email must be valid AND from company domain
                                    'email' => [
                                        'required',
                                        'email:rfc,dns',
                                        'regex:/^[a-z0-9._%+-]+@company\.com$/i',
                                    ],
                                    
                                    // Department: Case-insensitive validation (custom in_ci rule)
                                    'department' => [
                                        'required',
                                        'string',
                                        'in_ci:Engineering,Marketing,Sales,HR,Finance,Operations',
                                    ],
                                    
                                    // Salary: Numeric with range validation
                                    'salary' => [
                                        'required',
                                        'numeric',
                                        'min:0',
                                        'max:999999.99',
                                    ],
                                    
                                    // Hire date: Valid date, not in future, after company founding
                                    'hire_date' => [
                                        'required',
                                        'date_format:Y-m-d',
                                        'after:2020-01-01',  // Company founded in 2020
                                        'before_or_equal:today',
                                    ],
                                    
                                    // Phone: Custom regex rule for PH phone numbers
                                    'phone' => [
                                        'required',
                                        'custom:valid_phone_ph',
                                    ],
                                    
                                    // Multi-field expression validation:
                                    // Engineering dept must have salary >= 50k
                                    'salary' => [
                                        'required',
                                        'numeric',
                                        'min:0',
                                        'max:999999.99',
                                        'custom:engineering_salary_minimum',
                                    ],
                                ],
                            ],
                            
                            // Transformations: Clean and normalize data
                            'transformations' => [
                                'uppercase' => ['department'], // Standardize department names
                                'trim' => ['email', 'first_name', 'last_name'], // Remove whitespace
                                'integer' => ['salary'], // Convert salary to integer
                            ],
                            
                            'export_json' => true,
                        ]],
                        // Skip classification for CSV (no processor needed)
                        ['id' => null, 'type' => 'classification', 'config' => []],
                        // Skip extraction for CSV (data already structured)
                        ['id' => null, 'type' => 'extraction', 'config' => []],
                        // Optional: Add validation if needed
                        ['id' => null, 'type' => 'validation', 'config' => []],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify column headers', 'required' => true],
                    ['title' => 'Check for duplicate entries', 'required' => false],
                ],
                'allowed_mime_types' => [
                    'text/csv',
                    'text/plain',
                    'application/csv',
                    'application/vnd.ms-excel',
                ],
                'max_file_size_bytes' => 52428800, // 50MB for large CSV files
                'settings' => [
                    'queue' => 'default',
                ],
                'max_concurrent_jobs' => 5,
                'retention_days' => 90,
                'published_at' => now(),
            ],
            [
                'name' => 'Customer Data Import with Regex Extraction',
                'slug' => 'customer-data-regex-import',
                'description' => 'Import customer data with regex-based transformations (phone extraction, date reformatting, email domain extraction)',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        // CSV Importer with regex transformations
                        ['id' => $processors['csv-importer'] ?? null, 'type' => 'ocr', 'config' => [
                            'delimiter' => ',',
                            'has_headers' => true,
                            'date_columns' => ['registration_date'],
                            'date_format' => 'Y-m-d',
                            
                            // Transformations with REGEX (applied FIRST)
                            'transformations' => [
                                // Regex transformations applied BEFORE simple transformations
                                // NOTE: Transformations replace source fields IN-PLACE
                                'regex_transformations' => [
                                    // Extract area code from phone (replaces phone field)
                                    // Input: '+639171234567' → Output: '917'
                                    'phone' => [
                                        'type' => 'extract',
                                        'pattern' => '/^(\+63|0)?(9\d{2})\d{7}$/',
                                        'group' => 2,
                                    ],
                                    
                                    // Reformat registration date from MM/DD/YYYY to YYYY-MM-DD
                                    // Input: '12/25/2023' → Output: '2023-12-25'
                                    'registration_date' => [
                                        'type' => 'replace',
                                        'pattern' => '/^(\d{2})\/(\d{2})\/(\d{4})$/',
                                        'replacement' => '$3-$1-$2',
                                    ],
                                    
                                    // Extract domain from email (replaces email field)
                                    // Input: 'john@company.com' → Output: 'company.com'
                                    'email' => [
                                        'type' => 'extract',
                                        'pattern' => '/@(.+)$/',
                                        'group' => 1,
                                    ],
                                    
                                    // Split full name into first_name and last_name (creates new fields)
                                    // Input: 'Juan Dela Cruz' → first_name='Juan', last_name='Dela Cruz'
                                    'full_name' => [
                                        'type' => 'split',
                                        'pattern' => '/\s+/',
                                        'output_fields' => ['first_name', 'last_name'],
                                        'remove_original' => false,
                                    ],
                                    
                                    // Extract hashtags from bio (replaces bio field)
                                    // Input: '#php #laravel #vue' → Output: 'php,laravel,vue'
                                    'bio' => [
                                        'type' => 'extract_all',
                                        'pattern' => '/#(\w+)/',
                                        'group' => 1,
                                        'output' => 'comma_separated',
                                    ],
                                    
                                    // Remove EMP- prefix from employee_id
                                    // Input: 'EMP-001234' → Output: '001234'
                                    'employee_id' => [
                                        'type' => 'replace',
                                        'pattern' => '/^EMP-/',
                                        'replacement' => '',
                                    ],
                                ],
                                
                                // Simple transformations (applied AFTER regex)
                                'uppercase' => ['department'],
                                'trim' => ['email', 'bio'],
                                'integer' => ['age'],
                            ],
                            
                            // Validation rules (applied AFTER transformations)
                            'filters' => [
                                'validation_rules' => [
                                    'email' => ['required', 'string'],  // Will be domain only
                                    'phone' => ['required', 'string', 'size:3'],  // Will be area code only
                                    'first_name' => ['required', 'string'],  // Created by split
                                    'last_name' => ['required', 'string'],   // Created by split
                                    'full_name' => ['required', 'string'],   // Original remains
                                    'employee_id' => ['required', 'string'], // Prefix removed
                                    'department' => ['required', 'string'],
                                    'age' => ['required', 'integer', 'min:18', 'max:100'],
                                    'registration_date' => ['required', 'date_format:Y-m-d'],
                                    'bio' => ['required', 'string'],  // Will be comma-separated hashtags
                                ],
                            ],
                            
                            'export_json' => true,
                        ]],
                        ['id' => null, 'type' => 'classification', 'config' => []],
                        ['id' => null, 'type' => 'extraction', 'config' => []],
                        ['id' => null, 'type' => 'validation', 'config' => []],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify regex transformations applied correctly', 'required' => true],
                    ['title' => 'Check extracted area codes', 'required' => false],
                    ['title' => 'Verify date reformatting', 'required' => false],
                ],
                'allowed_mime_types' => [
                    'text/csv',
                    'text/plain',
                    'application/csv',
                ],
                'max_file_size_bytes' => 10485760, // 10MB
                'settings' => [
                    'queue' => 'default',
                ],
                'max_concurrent_jobs' => 3,
                'retention_days' => 90,
                'published_at' => now(),
            ],
            [
                'name' => 'Employee CSV Import (Filipino)',
                'slug' => 'employee-csv-import-fil',
                'description' => 'Bulk import employee data with Filipino validation messages',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['csv-importer'] ?? null, 'type' => 'ocr', 'config' => [
                            'delimiter' => ',',
                            'has_headers' => true,
                            'date_columns' => ['hire_date'],
                            'date_format' => 'Y-m-d',
                            'filters' => [
                                'validation_rules' => [
                                    'first_name' => ['required', 'string', 'min:2', 'max:100'],
                                    'last_name' => ['required', 'string', 'min:2', 'max:100'],
                                    'email' => ['required', 'email:rfc,dns'],
                                    'department' => ['required', 'string', 'in_ci:Engineering,Marketing,Sales,HR,Finance,Operations'],
                                    'salary' => ['required', 'numeric', 'min:0', 'max:999999.99', 'custom:engineering_salary_minimum'],
                                    'hire_date' => ['required', 'date_format:Y-m-d', 'after:2020-01-01', 'before_or_equal:today'],
                                    'phone' => ['required', 'custom:valid_phone_ph'],
                                ],
                            ],
                            'transformations' => [
                                'uppercase' => ['department'],
                                'trim' => ['email', 'first_name', 'last_name'],
                                'integer' => ['salary'],
                            ],
                            'export_json' => true,
                        ]],
                        ['id' => null, 'type' => 'classification', 'config' => []],
                        ['id' => null, 'type' => 'extraction', 'config' => []],
                        ['id' => null, 'type' => 'validation', 'config' => []],
                    ],
                ],
                'allowed_mime_types' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
                'max_file_size_bytes' => 52428800,
                'settings' => [
                    'queue' => 'default',
                    'locale' => 'fil', // Filipino locale
                ],
                'max_concurrent_jobs' => 5,
                'retention_days' => 90,
                'published_at' => now(),
            ],
            [
                'name' => 'Employee CSV Import (Spanish)',
                'slug' => 'employee-csv-import-es',
                'description' => 'Bulk import employee data with Spanish validation messages',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['csv-importer'] ?? null, 'type' => 'ocr', 'config' => [
                            'delimiter' => ',',
                            'has_headers' => true,
                            'date_columns' => ['hire_date'],
                            'date_format' => 'Y-m-d',
                            'filters' => [
                                'validation_rules' => [
                                    'first_name' => ['required', 'string', 'min:2', 'max:100'],
                                    'last_name' => ['required', 'string', 'min:2', 'max:100'],
                                    'email' => ['required', 'email:rfc,dns'],
                                    'department' => ['required', 'string', 'in_ci:Engineering,Marketing,Sales,HR,Finance,Operations'],
                                    'salary' => ['required', 'numeric', 'min:0', 'max:999999.99', 'custom:engineering_salary_minimum'],
                                    'hire_date' => ['required', 'date_format:Y-m-d', 'after:2020-01-01', 'before_or_equal:today'],
                                    'phone' => ['required', 'custom:valid_phone_ph'],
                                ],
                            ],
                            'transformations' => [
                                'uppercase' => ['department'],
                                'trim' => ['email', 'first_name', 'last_name'],
                                'integer' => ['salary'],
                            ],
                            'export_json' => true,
                        ]],
                        ['id' => null, 'type' => 'classification', 'config' => []],
                        ['id' => null, 'type' => 'extraction', 'config' => []],
                        ['id' => null, 'type' => 'validation', 'config' => []],
                    ],
                ],
                'allowed_mime_types' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
                'max_file_size_bytes' => 52428800,
                'settings' => [
                    'queue' => 'default',
                    'locale' => 'es', // Spanish locale
                ],
                'max_concurrent_jobs' => 5,
                'retention_days' => 90,
                'published_at' => now(),
            ],
            [
                'name' => 'Test eKYC Campaign',
                'slug' => 'test-ekyc-campaign',
                'description' => 'Test campaign for eKYC verification with document signing',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        ['id' => $processors['ekyc-verification'] ?? null, 'type' => 'validation', 'config' => [
                            'country' => 'PH',
                            'contact_fields' => [
                                'mobile' => 'required',
                                'name' => 'required',
                                'email' => 'optional',
                            ],
                        ]],
                        // Additional processors can be added here after KYC approval
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify KYC completion', 'required' => true],
                    ['title' => 'Check ID verification', 'required' => true],
                    ['title' => 'Verify contact information', 'required' => false],
                ],
                'allowed_mime_types' => [
                    'application/pdf',
                    'image/png',
                    'image/jpeg',
                ],
                'max_file_size_bytes' => 10485760, // 10MB
                'settings' => [
                    'queue' => 'default',
                    'locale' => 'en',
                ],
                'max_concurrent_jobs' => 5,
                'retention_days' => 365,
                'published_at' => now(),
            ],
            [
                'name' => 'ENF Electronic Signature',
                'slug' => 'e-signature',
                'description' => 'Electronic Notarization Framework: KYC verification + document signing with blockchain timestamps',
                'state' => \App\States\Campaign\ActiveCampaignState::class,
                'pipeline_config' => [
                    'processors' => [
                        // Step 1: eKYC Verification (generates transaction_id)
                        ['id' => $processors['ekyc-verification'] ?? null, 'type' => 'validation', 'config' => [
                            'country' => 'PH',
                            'transaction_id_prefix' => 'ENF',
                            'contact_fields' => [
                                'mobile' => 'required',
                                'name' => 'required',
                                'email' => 'optional',
                            ],
                        ]],
                        // Step 2: Electronic Signature (requires KYC approval)
                        // NOTE: This processor waits for HyperVerge webhook callback
                        // Then automatically signs document with KYC data
                        ['id' => $processors['electronic-signature'] ?? null, 'type' => 'signing', 'config' => [
                            'transaction_id' => '{{ekyc-verification.transaction_id}}', // From previous processor
                            'tile' => 1, // Bottom-right signature position
                            'metadata' => [
                                'notarization_type' => 'ENF',
                                'document_category' => 'Legal',
                            ],
                        ]],
                    ],
                ],
                'checklist_template' => [
                    ['title' => 'Verify signer identity via KYC', 'required' => true],
                    ['title' => 'Review signed document with QR watermark', 'required' => true],
                    ['title' => 'Confirm blockchain timestamp', 'required' => false],
                ],
                'allowed_mime_types' => [
                    'application/pdf', // Only PDF for signing
                ],
                'max_file_size_bytes' => 20971520, // 20MB for legal documents
                'settings' => [
                    'queue' => 'high-priority',
                    'locale' => 'en',
                    'workflow' => 'sequential', // Run processors in order with dependency checking
                ],
                'max_concurrent_jobs' => 3,
                'retention_days' => 2555, // 7 years for legal compliance
                'published_at' => now(),
            ],
        ];

        foreach ($campaigns as $campaignData) {
            Campaign::updateOrCreate(
                ['slug' => $campaignData['slug']],
                $campaignData
            );
        }

        $this->command->info('Seeded '.count($campaigns).' campaigns');
    }

    /**
     * Seed campaigns via campaign:import command (TEST MODE).
     *
     * This tests the campaign:import command by creating campaigns
     * using the same flow as importing from JSON/YAML files.
     */
    private function seedCampaignsViaImportCommand(): void
    {
        // Get current tenant from context
        $tenant = TenantContext::current();
        if (! $tenant) {
            $this->command->error('No tenant context found.');

            return;
        }

        // Get processor ID mappings (slug => database ID)
        $processors = Processor::pluck('id', 'slug')->toArray();
        if (empty($processors)) {
            $this->command->warn('No processors found. Run ProcessorSeeder first.');

            return;
        }

        $this->command->info('Testing campaign:import command...');

        // Define campaigns in import format (state strings, type strings, no processor IDs)
        $campaignDefinitions = [
            [
                'name' => 'ENF Electronic Signature',
                'slug' => 'e-signature',
                'description' => 'Electronic Notarization Framework: KYC verification + document signing with blockchain timestamps',
                'type' => 'custom',
                'state' => 'active',
                'processors' => [
                    // Step 1: eKYC Verification
                    ['id' => 'ekyc-step', 'type' => 'ekycverification', 'config' => [
                        'country' => 'PH',
                        'transaction_id_prefix' => 'ENF',
                        'contact_fields' => [
                            'mobile' => 'required',
                            'name' => 'required',
                            'email' => 'optional',
                        ],
                    ]],
                    // Step 2: Electronic Signature
                    ['id' => 'signature-step', 'type' => 'electronicsignature', 'config' => [
                        'transaction_id' => '{{ekyc-verification.transaction_id}}',
                        'tile' => 1,
                        'metadata' => [
                            'notarization_type' => 'ENF',
                            'document_category' => 'Legal',
                        ],
                    ]],
                ],
                'checklist_template' => [
                    ['title' => 'Verify signer identity via KYC', 'required' => true],
                    ['title' => 'Review signed document with QR watermark', 'required' => true],
                    ['title' => 'Confirm blockchain timestamp', 'required' => false],
                ],
                'allowed_mime_types' => [
                    'application/pdf', // Only PDF for signing
                ],
                'max_file_size_bytes' => 20971520, // 20MB for legal documents
                'settings' => [
                    'queue' => 'high-priority',
                    'locale' => 'en',
                    'workflow' => 'sequential',
                ],
                'max_concurrent_jobs' => 3,
                'retention_days' => 2555, // 7 years for legal compliance
            ],
        ];

        foreach ($campaignDefinitions as $campaignData) {
            // Check if campaign already exists
            if (Campaign::where('slug', $campaignData['slug'])->exists()) {
                $this->command->warn("Campaign '{$campaignData['slug']}' already exists. Skipping.");

                continue;
            }

            try {
                // Call campaign:import command with JSON string (no temp files!)
                Artisan::call('campaign:import', [
                    '--json' => json_encode($campaignData),
                    '--tenant' => $tenant->id,
                ]);

                $output = Artisan::output();
                $this->command->line($output);

                // CRITICAL: Update processor IDs in pipeline_config
                // campaign:import uses step IDs, but workflow needs database IDs
                $campaign = Campaign::where('slug', $campaignData['slug'])->first();
                if ($campaign) {
                    $pipelineConfig = $campaign->pipeline_config;
                    foreach ($pipelineConfig['processors'] as $index => $processor) {
                        // Map processor type to database ID
                        $processorSlug = $this->getProcessorSlugFromType($processor['type']);
                        if (isset($processors[$processorSlug])) {
                            $pipelineConfig['processors'][$index]['id'] = $processors[$processorSlug];
                        } else {
                            $this->command->warn("Processor '{$processor['type']}' (slug: {$processorSlug}) not found in database.");
                        }
                    }
                    $campaign->update(['pipeline_config' => $pipelineConfig]);
                    $this->command->info("  → Updated processor IDs for workflow execution");
                }

                $this->command->info("✓ Imported: {$campaignData['name']}");
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to import '{$campaignData['name']}': {$e->getMessage()}");
            }
        }

        $this->command->info('Campaign import test completed.');
    }

    /**
     * Map processor type to slug.
     *
     * campaign:import uses ProcessorRegistry which maps types to processors.
     * This method replicates that mapping for database ID resolution.
     */
    private function getProcessorSlugFromType(string $type): string
    {
        // Map processor types to slugs (must match ProcessorRegistry)
        return match ($type) {
            'ocr' => 'tesseract-ocr',
            'classification' => 'document-classifier',
            'extraction' => 'data-extractor',
            'dataenricher' => 'data-enricher',
            'ekycverification' => 'ekyc-verification',
            'electronicsignature' => 'electronic-signature',
            'emailnotifier' => 'email-notifier',
            'openaivision' => 'openai-vision-ocr',
            's3storage' => 's3-storage',
            'schemavalidator' => 'schema-validator',
            'csvimport' => 'csv-importer',
            default => $type, // Fallback to type as slug
        };
    }
}
