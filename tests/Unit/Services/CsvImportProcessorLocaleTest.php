<?php

namespace Tests\Unit\Services;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\Tenant;
use App\Services\Processors\PortPHP\CsvImportProcessor;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for CsvImportProcessor locale detection.
 */
class CsvImportProcessorLocaleTest extends TestCase
{
    private CsvImportProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new CsvImportProcessor();
    }

    /**
     * Test locale detection priority: Campaign > Tenant > Default.
     */
    public function test_detectLocale_uses_campaign_settings_first(): void
    {
        // Create mock tenant with locale 'en'
        $tenant = $this->createMockTenant(['locale' => 'en']);
        
        // Create mock campaign with locale 'fil' (should override tenant)
        $campaign = $this->createMockCampaign(['locale' => 'fil']);
        
        // Create mock document
        $document = $this->createMockDocument($campaign);
        
        // Use reflection to call protected method
        $locale = $this->invokeProtectedMethod($this->processor, 'detectLocale', [$document, $tenant]);
        
        $this->assertEquals('fil', $locale, 'Should use campaign locale over tenant locale');
    }

    public function test_detectLocale_falls_back_to_tenant_settings(): void
    {
        // Create mock tenant with locale 'es'
        $tenant = $this->createMockTenant(['locale' => 'es']);
        
        // Create mock campaign WITHOUT locale
        $campaign = $this->createMockCampaign([]);
        
        // Create mock document
        $document = $this->createMockDocument($campaign);
        
        // Use reflection to call protected method
        $locale = $this->invokeProtectedMethod($this->processor, 'detectLocale', [$document, $tenant]);
        
        $this->assertEquals('es', $locale, 'Should fall back to tenant locale when campaign has no locale');
    }

    public function test_detectLocale_defaults_to_english(): void
    {
        // Create mock tenant WITHOUT locale
        $tenant = $this->createMockTenant([]);
        
        // Create mock campaign WITHOUT locale
        $campaign = $this->createMockCampaign([]);
        
        // Create mock document
        $document = $this->createMockDocument($campaign);
        
        // Use reflection to call protected method
        $locale = $this->invokeProtectedMethod($this->processor, 'detectLocale', [$document, $tenant]);
        
        $this->assertEquals('en', $locale, 'Should default to English when neither campaign nor tenant has locale');
    }

    public function test_detectLocale_handles_null_campaign(): void
    {
        // Create mock tenant with locale 'fil'
        $tenant = $this->createMockTenant(['locale' => 'fil']);
        
        // Create mock document WITHOUT campaign
        $document = $this->createMockDocument(null);
        
        // Use reflection to call protected method
        $locale = $this->invokeProtectedMethod($this->processor, 'detectLocale', [$document, $tenant]);
        
        $this->assertEquals('fil', $locale, 'Should use tenant locale when document has no campaign');
    }

    public function test_detectLocale_priority_order(): void
    {
        // Test all combinations to verify priority order
        $testCases = [
            // [campaign_locale, tenant_locale, expected]
            ['fil', 'es', 'fil'],  // Campaign wins
            ['fil', null, 'fil'],  // Campaign wins
            [null, 'es', 'es'],    // Tenant wins
            [null, null, 'en'],    // Default wins
        ];

        foreach ($testCases as [$campaignLocale, $tenantLocale, $expected]) {
            $tenant = $this->createMockTenant($tenantLocale ? ['locale' => $tenantLocale] : []);
            $campaign = $this->createMockCampaign($campaignLocale ? ['locale' => $campaignLocale] : []);
            $document = $this->createMockDocument($campaign);

            $locale = $this->invokeProtectedMethod($this->processor, 'detectLocale', [$document, $tenant]);

            $this->assertEquals(
                $expected,
                $locale,
                "Failed for campaign={$campaignLocale}, tenant={$tenantLocale}"
            );
        }
    }

    /**
     * Helper: Create mock tenant with settings.
     */
    private function createMockTenant(array $settings): object
    {
        $tenant = Mockery::mock('stdClass');
        $tenant->id = '01test123';
        $tenant->slug = 'test-tenant';
        $tenant->name = 'Test Tenant';
        $tenant->settings = $settings;
        
        return $tenant;
    }

    /**
     * Helper: Create mock campaign with settings.
     */
    private function createMockCampaign(array $settings): object
    {
        $campaign = Mockery::mock('stdClass');
        $campaign->id = '01test456';
        $campaign->slug = 'test-campaign';
        $campaign->name = 'Test Campaign';
        $campaign->settings = $settings;
        
        return $campaign;
    }

    /**
     * Helper: Create mock document with campaign.
     */
    private function createMockDocument(?object $campaign): Document
    {
        $document = Mockery::mock(Document::class)->makePartial();
        $document->campaign = $campaign;
        
        return $document;
    }

    /**
     * Helper: Invoke protected/private method using reflection.
     */
    private function invokeProtectedMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
