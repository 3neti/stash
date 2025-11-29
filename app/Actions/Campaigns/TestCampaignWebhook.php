<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Jobs\DispatchWebhook;
use App\Models\Campaign;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Send test webhook payload to campaign webhook URL.
 */
class TestCampaignWebhook
{
    use AsAction;

    /**
     * Authorize the action.
     */
    public function authorize(ActionRequest $request): bool
    {
        return $request->user() !== null;
    }

    /**
     * Send test webhook.
     */
    public function handle(Campaign $campaign): array
    {
        if (! $campaign->webhook) {
            return [
                'success' => false,
                'message' => 'No webhook URL configured for this campaign',
            ];
        }

        $testPayload = [
            'event' => 'webhook.test',
            'timestamp' => now()->toISOString(),
            'data' => [
                'campaign' => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                ],
                'message' => 'This is a test webhook from Stash/DeadDrop',
            ],
        ];

        // Dispatch test webhook
        DispatchWebhook::dispatch($campaign, 'webhook.test', $testPayload);

        return [
            'success' => true,
            'message' => 'Test webhook dispatched successfully',
            'webhook_url' => $campaign->webhook,
        ];
    }

    /**
     * Handle as controller.
     */
    public function asController(ActionRequest $request, Campaign $campaign): array
    {
        return $this->handle($campaign);
    }
}
