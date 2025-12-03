<?php

namespace Database\Seeders;

use App\Models\CustomValidationRule;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class CustomValidationRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first tenant (should be the default tenant created from .env)
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command->warn('No tenant found. Skipping custom validation rules seeding.');

            return;
        }

        $rules = [
            [
                'tenant_id' => $tenant->id,
                'name' => 'valid_phone_ph',
                'label' => 'Valid Philippine Phone Number',
                'description' => 'Validates PH mobile numbers in formats: +639123456789, 09123456789, 9123456789',
                'type' => 'regex',
                'config' => [
                    'pattern' => '/^(\+63|0)?9\d{9}$/',
                    'message' => 'Must be a valid Philippine phone number (e.g., +639123456789 or 09123456789)',
                ],
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'valid_employee_id',
                'label' => 'Valid Employee ID',
                'description' => 'Validates employee IDs in format: EMP-123456 (6 digits)',
                'type' => 'regex',
                'config' => [
                    'pattern' => '/^EMP-\d{6}$/',
                    'message' => 'Employee ID must be in format EMP-123456',
                ],
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'valid_zip_code_ph',
                'label' => 'Valid Philippine ZIP Code',
                'description' => 'Validates 4-digit PH ZIP codes',
                'type' => 'regex',
                'config' => [
                    'pattern' => '/^\d{4}$/',
                    'message' => 'ZIP code must be exactly 4 digits',
                ],
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'strong_password',
                'label' => 'Strong Password',
                'description' => 'At least 8 characters with uppercase, lowercase, number, and special character',
                'type' => 'regex',
                'config' => [
                    'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
                    'message' => 'Password must be at least 8 characters with uppercase, lowercase, number, and special character',
                ],
                'is_active' => true,
            ],
            [
                'tenant_id' => $tenant->id,
                'name' => 'valid_hex_color',
                'label' => 'Valid Hex Color Code',
                'description' => 'Validates hex color codes (#RGB or #RRGGBB)',
                'type' => 'regex',
                'config' => [
                    'pattern' => '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
                    'message' => 'Must be a valid hex color code (e.g., #FF5733 or #F57)',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($rules as $ruleData) {
            CustomValidationRule::updateOrCreate(
                [
                    'tenant_id' => $ruleData['tenant_id'],
                    'name' => $ruleData['name'],
                ],
                $ruleData
            );
        }

        $this->command->info('Seeded '.count($rules).' custom validation rules');
    }
}
