<?php

declare(strict_types=1);

namespace Tests\Feature\API;

use App\Models\Contact;
use App\Models\KycTransaction;
use App\Models\Tenant;
use Tests\DeadDropTestCase;

class KycContactControllerTest extends DeadDropTestCase
{
    protected Tenant $tenant;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'database' => 'tenant_test',
        ]);
        
        // Initialize tenant context
        app(\App\Services\Tenancy\TenancyService::class)->initializeTenant($this->tenant);
    }
    
    public function test_returns_contact_data_when_ready(): void
    {
        // Create KYC transaction in central DB
        $kycTransaction = KycTransaction::create([
            'transaction_id' => 'TEST-' . time(),
            'tenant_id' => $this->tenant->id,
            'document_id' => 'test-doc-id',
            'status' => 'auto_approved',
        ]);
        
        // Create Contact in tenant DB with same transaction ID
        $data = [
            'name' => 'Test User',
            'kyc_transaction_id' => $kycTransaction->transaction_id,
            'kyc_status' => 'approved',
            'kyc_completed_at' => now(),
        ];
        
        $contact = Contact::create($data);
        
        // Verify contact exists before calling API
        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'kyc_transaction_id' => $kycTransaction->transaction_id,
        ], 'tenant');
        
        // Call API
        $response = $this->getJson("/api/kyc/{$kycTransaction->transaction_id}/contact");
        
        // Assertions
        $response->assertOk();
        $response->assertJson([
            'ready' => true,
        ]);
        
        $response->assertJsonStructure([
            'ready',
            'contact' => [
                'id',
                'name',
                'kyc_status',
                'kyc_completed_at',
                'id_card_urls',
                'selfie_url',
            ],
        ]);
    }
    
    public function test_returns_not_ready_when_contact_not_found(): void
    {
        // Create KYC transaction without Contact
        $kycTransaction = KycTransaction::create([
            'transaction_id' => 'TEST-' . time(),
            'tenant_id' => $this->tenant->id,
            'document_id' => 'test-doc-id',
            'status' => 'auto_approved',
        ]);
        
        // Call API
        $response = $this->getJson("/api/kyc/{$kycTransaction->transaction_id}/contact");
        
        // Assertions
        $response->assertOk();
        $response->assertJson([
            'ready' => false,
            'message' => 'Still processing your verification. Please wait a moment and refresh.',
        ]);
    }
    
    public function test_returns_404_when_transaction_not_found(): void
    {
        $response = $this->getJson("/api/kyc/NON-EXISTENT-TX/contact");
        
        $response->assertNotFound();
        $response->assertJson([
            'ready' => false,
            'message' => 'Transaction not found.',
        ]);
    }
}
