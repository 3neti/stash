<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dispatch webhook to campaign's configured URL.
 *
 * Supports retries with exponential backoff and HMAC signing.
 */
class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // seconds

    public function __construct(
        public Campaign $campaign,
        public string $eventType,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $webhookUrl = $this->campaign->webhook;

        if (! $webhookUrl) {
            Log::info('Webhook not configured for campaign', [
                'campaign_id' => $this->campaign->id,
                'event' => $this->eventType,
            ]);

            return;
        }

        // Check if event is enabled in campaign settings
        if (! $this->isEventEnabled()) {
            Log::info('Webhook event not enabled for campaign', [
                'campaign_id' => $this->campaign->id,
                'event' => $this->eventType,
            ]);

            return;
        }

        $webhookDelivery = WebhookDelivery::create([
            'campaign_id' => $this->campaign->id,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'attempted_at' => now(),
            'attempts' => $this->attempts(),
        ]);

        try {
            $signature = $this->generateSignature();

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->eventType,
                    'X-Webhook-Delivery-Id' => $webhookDelivery->id,
                ])
                ->post($webhookUrl, $this->payload);

            if ($response->successful()) {
                $webhookDelivery->update([
                    'response_status' => $response->status(),
                    'response_body' => $response->body(),
                    'delivered_at' => now(),
                ]);

                Log::info('Webhook delivered successfully', [
                    'campaign_id' => $this->campaign->id,
                    'event' => $this->eventType,
                    'delivery_id' => $webhookDelivery->id,
                ]);
            } else {
                throw new \Exception("Webhook returned {$response->status()}: {$response->body()}");
            }
        } catch (\Exception $e) {
            $webhookDelivery->update([
                'response_status' => $response->status() ?? null,
                'response_body' => $e->getMessage(),
                'failed_at' => $this->attempts() >= $this->tries ? now() : null,
            ]);

            Log::error('Webhook delivery failed', [
                'campaign_id' => $this->campaign->id,
                'event' => $this->eventType,
                'delivery_id' => $webhookDelivery->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff * $this->attempts());
            } else {
                $this->fail($e);
            }
        }
    }

    /**
     * Generate HMAC signature for payload.
     */
    protected function generateSignature(): string
    {
        $secret = $this->campaign->settings['webhooks']['secret'] ?? '';

        return hash_hmac('sha256', json_encode($this->payload), $secret);
    }

    /**
     * Check if event type is enabled in campaign settings.
     */
    protected function isEventEnabled(): bool
    {
        $enabledEvents = $this->campaign->settings['notifications']['events'] ?? [
            'document.processing.completed',
            'document.processing.failed',
        ];

        return in_array($this->eventType, $enabledEvents, true);
    }

    /**
     * Calculate backoff for retries (exponential).
     */
    public function backoff(): array
    {
        return [60, 120, 240]; // 1min, 2min, 4min
    }
}
