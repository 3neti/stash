<?php

namespace App\Notifications;

use App\Jobs\Middleware\InitializeTenantContext;
use App\Models\Campaign;
use App\Models\Document;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a document has been processed
 * 
 * Note: Queuing is disabled for now due to multi-tenancy serialization complexity.
 * Use Laravel Workflow for async notification handling instead.
 */
class DocumentProcessedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Document $document,
        public Campaign $campaign,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Get channels from campaign notification settings
        $channels = $this->campaign->notification_settings['channels'] ?? ['database'];

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $filename = $this->document->filename ?? $this->document->original_filename;
        
        return (new MailMessage)
            ->subject("Document Processed: {$filename}")
            ->line("Your document '{$filename}' has been successfully processed.")
            ->line("Campaign: {$this->campaign->name}")
            ->action('View Document', url("/documents/{$this->document->id}"))
            ->line('Thank you for using Stash!');
    }

    /**
     * Get the SMS representation of the notification.
     *
     * @return string|array<string, mixed>
     */
    public function toSms(object $notifiable): string|array
    {
        $filename = $this->document->filename ?? $this->document->original_filename;
        
        return "Document '{$filename}' has been processed successfully. Campaign: {$this->campaign->name}";
    }

    /**
     * Get the array representation of the notification (for database channel).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $filename = $this->document->filename ?? $this->document->original_filename;
        
        return [
            'document_id' => $this->document->id,
            'document_filename' => $filename,
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name,
            'message' => "Document '{$filename}' has been processed successfully.",
        ];
    }

    /**
     * Optional: Specify SMS driver for this notification
     */
    public function smsDriver(): string
    {
        // Use campaign-specific provider if set
        return $this->campaign->notification_settings['sms_provider'] ?? 'txtcmdr';
    }
}
