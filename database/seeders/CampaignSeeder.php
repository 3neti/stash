<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Processor;
use Illuminate\Database\Seeder;
use Illuminate\Validation\Rule;

class CampaignSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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
        ];

        foreach ($campaigns as $campaignData) {
            Campaign::updateOrCreate(
                ['slug' => $campaignData['slug']],
                $campaignData
            );
        }

        $this->command->info('Seeded '.count($campaigns).' campaigns');
    }
}
