<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\HyperVerge\Actions\LinkKYC\GenerateOnboardingLink;

/**
 * Test HyperVerge onboarding link generation.
 * 
 * This command directly calls the HyperVerge API to generate a KYC link
 * without going through the document processing workflow.
 * 
 * Usage:
 *   php artisan hyperverge:test-link
 *   php artisan hyperverge:test-link --test-mode
 *   php artisan hyperverge:test-link --workflow=workflow_ABC123
 */
class TestHyperVergeLink extends Command
{
    protected $signature = 'hyperverge:test-link 
                            {--test-mode : Use test mode (mock response)}
                            {--workflow= : Workflow ID (default: workflow_2nQDNT)}
                            {--transaction= : Transaction ID (default: auto-generated)}';

    protected $description = 'Test HyperVerge onboarding link generation';

    public function handle(): int
    {
        $this->info('🔗 Testing HyperVerge Link Generation');
        $this->newLine();

        // Get options
        $testMode = $this->option('test-mode');
        $workflowId = $this->option('workflow') ?? 'workflow_2nQDNT';
        $transactionId = $this->option('transaction') ?? 'test_' . time() . '_' . rand(1000, 9999);

        // Display configuration
        $this->table(
            ['Setting', 'Value'],
            [
                ['Test Mode', $testMode ? 'YES (mock)' : 'NO (real API)'],
                ['Workflow ID', $workflowId],
                ['Transaction ID', $transactionId],
                ['Base URL', config('hyperverge.base_url')],
                ['App ID', config('hyperverge.app_id')],
                ['App Key', str_repeat('*', 12) . substr(config('hyperverge.app_key'), -4)],
            ]
        );
        $this->newLine();

        // Temporarily override test mode if specified
        if ($testMode) {
            config(['hyperverge.test_mode' => true]);
            $this->warn('⚠️  Test mode enabled - API will not be called');
            $this->newLine();
        }

        try {
            $this->info('📡 Calling HyperVerge API...');
            
            $redirectUrl = config('app.url') . '/kyc/callback/test';
            
            // Option 1: Try without any options (like your working codebase)
            $this->line('Attempt 1: No options (default behavior)');
            try {
                $url1 = GenerateOnboardingLink::get(
                    transactionId: $transactionId . '_A1',
                    workflowId: $workflowId,
                    redirectUrl: $redirectUrl
                );
                
                $this->newLine();
                $this->info('✅ SUCCESS - Attempt 1!');
                $this->line('Generated URL:');
                $this->line($url1);
                $this->newLine();
                $this->line('Testing if link is accessible...');
                
                // Try to fetch the link to see if it redirects properly
                try {
                    $response = \Illuminate\Support\Facades\Http::get($url1);
                    $this->info('HTTP Status: ' . $response->status());
                    if ($response->successful()) {
                        $this->info('✅ Link is accessible!');
                    } else {
                        $this->warn('⚠️  Link returned status: ' . $response->status());
                    }
                } catch (\Throwable $httpE) {
                    $this->warn('⚠️  Could not test link: ' . $httpE->getMessage());
                }
                
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('❌ Attempt 1 failed: ' . $e->getMessage());
                $this->newLine();
            }

            // Option 2: Try with allowEmptyWorkflowInputs
            $this->line('Attempt 2: With allowEmptyWorkflowInputs = yes');
            try {
                $url2 = GenerateOnboardingLink::get(
                    transactionId: $transactionId . '_attempt2',
                    workflowId: $workflowId,
                    redirectUrl: $redirectUrl,
                    options: [
                        'allowEmptyWorkflowInputs' => 'yes',
                    ]
                );
                
                $this->newLine();
                $this->info('✅ SUCCESS!');
                $this->line('Generated URL:');
                $this->line($url2);
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('❌ Attempt 2 failed: ' . $e->getMessage());
                $this->newLine();
            }

            // Option 3: Try with validateWorkflowInputs = no
            $this->line('Attempt 3: With validateWorkflowInputs = no');
            try {
                $url3 = GenerateOnboardingLink::get(
                    transactionId: $transactionId . '_attempt3',
                    workflowId: $workflowId,
                    redirectUrl: $redirectUrl,
                    options: [
                        'validateWorkflowInputs' => 'no',
                        'allowEmptyWorkflowInputs' => 'yes',
                    ]
                );
                
                $this->newLine();
                $this->info('✅ SUCCESS!');
                $this->line('Generated URL:');
                $this->line($url3);
                // Do not exit; try attempt 4 as well and show both
            } catch (\Throwable $e) {
                $this->error('❌ Attempt 3 failed: ' . $e->getMessage());
                $this->newLine();
            }

            // Option 4: Omit workflowId (let credentials decide), with skip validation
            $this->line('Attempt 4: No workflowId param + validateWorkflowInputs = no');
            try {
                $url4 = GenerateOnboardingLink::get(
                    transactionId: $transactionId . '_attempt4',
                    workflowId: null,
                    redirectUrl: $redirectUrl,
                    options: [
                        'validateWorkflowInputs' => 'no',
                        'allowEmptyWorkflowInputs' => 'yes',
                    ]
                );
                
                $this->newLine();
                $this->info('✅ SUCCESS!');
                $this->line('Generated URL:');
                $this->line($url4);
                return self::SUCCESS;
            } catch (\Throwable $e) {
                $this->error('❌ Attempt 4 failed: ' . $e->getMessage());
                $this->newLine();
            }

            $this->newLine();
            $this->error('❌ All attempts failed. Check logs for details.');
            $this->line('Log file: storage/logs/laravel.log');
            
            return self::FAILURE;

        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('❌ Unexpected error: ' . $e->getMessage());
            $this->line('File: ' . $e->getFile() . ':' . $e->getLine());
            
            return self::FAILURE;
        }
    }
}
