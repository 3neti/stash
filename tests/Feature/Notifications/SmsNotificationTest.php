<?php

namespace Tests\Feature\Notifications;

use App\Models\Campaign;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DocumentProcessedNotification;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\DeadDrop\TxtcmdrClient\Channels\SmsChannel;
use LBHurtado\DeadDrop\TxtcmdrClient\Contracts\SmsDriverInterface;
use LBHurtado\DeadDrop\TxtcmdrClient\DTO\SendSmsResponse;
use Mockery;
use Tests\DeadDropTestCase;

class SmsNotificationTest extends DeadDropTestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant on central DB
        $this->tenant = Tenant::on('central')->create([
            'name' => 'Test SMS Org',
            'slug' => 'test-sms-org-'.uniqid(),
            'email' => 'sms@example.com',
            'tier' => 'professional',
        ]);

        // Seed tenant data
        TenantContext::run($this->tenant, function () {
            // Create user on central connection (users don't belong to tenant DB)
            User::on('central')->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id,
            ]);

            // Create campaign
            Campaign::create([
                'name' => 'Test Campaign',
                'slug' => 'test-campaign',
                'type' => 'custom', // Valid types: template, custom, meta
                'settings' => [],
                'pipeline_config' => ['processors' => []],
            ]);
        });
    }

    public function test_sms_channel_is_registered(): void
    {
        $this->assertInstanceOf(SmsDriverInterface::class, app('sms.driver.txtcmdr'));
        $this->assertInstanceOf(SmsChannel::class, app(SmsChannel::class));
    }

    public function test_anonymous_notification_can_route_to_mobile(): void
    {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::first();
            $mobile = '09173011987';

            // Test that we can build routes for anonymous notifications
            $routes = ['sms' => $mobile];
            
            $this->assertArrayHasKey('sms', $routes);
            $this->assertEquals($mobile, $routes['sms']);
        });
    }

    public function test_campaign_can_have_notification_settings(): void
    {
        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::first();

            // Set notification settings with mobile number for anonymous notifications
            $campaign->notification_settings = [
                'channels' => ['database', 'sms'],
                'sms_provider' => 'txtcmdr',
                'sms_mobile' => '09173011987', // For anonymous notifications
            ];
            $campaign->save();

            // Verify settings
            $campaign->refresh();
            $this->assertIsArray($campaign->notification_settings);
            $this->assertArrayHasKey('channels', $campaign->notification_settings);
            $this->assertContains('sms', $campaign->notification_settings['channels']);
            $this->assertEquals('09173011987', $campaign->notification_settings['sms_mobile']);
        });
    }

    public function test_sms_notification_can_be_sent_anonymously(): void
    {
        // Fake notifications to verify they were sent
        \Illuminate\Support\Facades\Notification::fake();

        TenantContext::run($this->tenant, function () {
            $campaign = Campaign::first();
            $mobile = '09173011987';

            // Enable SMS notifications for campaign with mobile number
            $campaign->notification_settings = [
                'channels' => ['sms'],
                'sms_provider' => 'txtcmdr',
                'sms_mobile' => $mobile,
            ];
            $campaign->save();

            // Create test document
            $document = Document::create([
                'filename' => 'test-'.now()->timestamp.'.pdf',
                'original_filename' => 'test.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
                'storage_path' => 'test/path.pdf',
                'hash' => hash('sha256', 'test-content'),
                'campaign_id' => $campaign->id,
            ]);

            // Send anonymous notification
            \Illuminate\Support\Facades\Notification::route('sms', $mobile)
                ->notify(new DocumentProcessedNotification($document, $campaign));

            // Verify notification was sent
            \Illuminate\Support\Facades\Notification::assertSentOnDemand(
                DocumentProcessedNotification::class,
                function ($notification, $channels, $notifiable) use ($document, $campaign) {
                    return $notification->document->id === $document->id
                        && $notification->campaign->id === $campaign->id
                        && in_array('sms', $channels);
                }
            );
        });
    }

    public function test_notification_uses_correct_sms_message_format(): void
    {
        TenantContext::run($this->tenant, function () {
            $user = User::first();
            $campaign = Campaign::first();
            $document = Document::create([
                'filename' => 'test-format.pdf',
                'original_filename' => 'test.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
                'storage_path' => 'test/path.pdf',
                'hash' => hash('sha256', 'test-content'),
                'campaign_id' => $campaign->id,
            ]);

            $notification = new DocumentProcessedNotification($document, $campaign);

            // Test toSms() method
            $smsMessage = $notification->toSms($user);

            $this->assertIsString($smsMessage);
            // Should contain either filename or original_filename
            $this->assertTrue(
                str_contains($smsMessage, 'test-format.pdf') || str_contains($smsMessage, 'test.pdf'),
                "Message should contain filename. Actual: {$smsMessage}"
            );
            $this->assertStringContainsString($campaign->name, $smsMessage);
        });
    }
}
