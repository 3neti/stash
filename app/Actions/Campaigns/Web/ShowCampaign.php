<?php

declare(strict_types=1);

namespace App\Actions\Campaigns\Web;

use App\Models\Campaign;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Show campaign details with documents.
 */
class ShowCampaign
{
    use AsAction;

    /**
     * Get campaign with documents.
     */
    public function handle(Campaign $campaign): Campaign
    {
        return $campaign->load(['documents' => function ($query) {
            $query->with('documentJob')->latest()->limit(20);
        }])->loadCount('documents');
    }

    /**
     * Handle as controller.
     */
    public function asController(Campaign $campaign): Campaign
    {
        return $this->handle($campaign);
    }
}
