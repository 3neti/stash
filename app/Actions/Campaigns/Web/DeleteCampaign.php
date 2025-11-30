<?php

declare(strict_types=1);

namespace App\Actions\Campaigns\Web;

use App\Models\Campaign;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Delete campaign (soft delete).
 */
class DeleteCampaign
{
    use AsAction;

    /**
     * Delete campaign.
     */
    public function handle(Campaign $campaign): bool
    {
        return $campaign->delete();
    }

    /**
     * Handle as controller.
     */
    public function asController(Campaign $campaign): bool
    {
        return $this->handle($campaign);
    }
}
