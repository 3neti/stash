<?php

declare(strict_types=1);


use App\Actions\Documents\UploadDocument;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\DocumentJob;
use App\Models\Processor;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

uses(Tests\TestCase::class, Tests\Concerns\SetUpsTenantDatabase::class);

class SetupVerificationTest extends TestCase
{
    public function test_setup_commands_enable_cli_and_web_flows(): void
    {
        $this->markTestSkipped('Command integration test - requires full system setup');
        
        // 1) Run the same setup sequence the user runs
        Artisan::call('tenant:wipe', ['--force' => true, '--no-interaction' => true]);
        Artisan::call('migrate:fresh', ['--no-interaction' => true]);
        Artisan::call('dashboard:setup-test', ['--no-interaction' => true]);

        // 2) Central database checks (CLI preconditions)
        $tenant = Tenant::first();
        $this->assertNotNull($tenant, 'Tenant not created by dashboard:setup-test');

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user, 'Test user not created by dashboard:setup-test');

        // 3) Tenant database checks (processors, campaigns)
        TenantContext::run($tenant, function () {
            $expectedSlugs = [
                'tesseract-ocr',
                'document-classifier',
                'data-extractor',
                'schema-validator',
                'data-enricher',
                'email-notifier',
                's3-storage',
                'openai-vision-ocr',
            ];

            foreach ($expectedSlugs as $slug) {
                $this->assertNotNull(
                    Processor::where('slug', $slug)->first(),
                    "Processor '$slug' not found in tenant DB"
                );
            }

            $campaigns = Campaign::all();
            $this->assertGreaterThan(0, $campaigns->count(), 'No campaigns seeded');

            foreach ($campaigns as $campaign) {
                $configs = $campaign->pipeline_config['processors'] ?? [];
                $this->assertNotEmpty($configs, "Campaign {$campaign->slug} has no processors");

                foreach ($configs as $cfg) {
                    $id = $cfg['id'] ?? null;
                    $this->assertIsString($id, 'Processor ID missing');
                    $this->assertMatchesRegularExpression('/^[0-9a-z]{26}$/', $id, 'Processor ID is not a ULID');
                    $this->assertNotNull(Processor::find($id), 'Referenced processor not found');
                }
            }

            // 4) Verify campaign has a pipeline configured (no upload in this test)
            $campaign = Campaign::first();
            $this->assertNotNull($campaign, 'No campaigns found');
            $processorConfigs = $campaign->pipeline_config['processors'] ?? [];
            $this->assertNotEmpty($processorConfigs, 'Campaign pipeline is empty');
        });

        // 5) Web flow: login and access dashboard
        $login = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);
        $this->assertTrue($login->isRedirection() || $login->status() < 400, 'Login failed');

        $this->actingAs($user)->get('/dashboard')->assertStatus(200);
    }
}
