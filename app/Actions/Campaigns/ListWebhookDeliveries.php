<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List webhook delivery history for campaign.
 */
class ListWebhookDeliveries
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
     * List webhook deliveries.
     */
    public function handle(Campaign $campaign, int $perPage = 15): array
    {
        $deliveries = \App\Models\WebhookDelivery::where('campaign_id', $campaign->id)
            ->orderByDesc('attempted_at')
            ->paginate($perPage);

        return $deliveries->toArray();
    }

    /**
     * Handle as controller.
     */
    public function asController(ActionRequest $request, Campaign $campaign): array
    {
        return $this->handle(
            $campaign,
            (int) $request->input('per_page', 15)
        );
    }
}
