<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Revoke all API tokens for campaign.
 */
class RevokeCampaignToken
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
     * Revoke all campaign API tokens.
     */
    public function handle(Campaign $campaign): array
    {
        $count = $campaign->tokens()->count();
        $campaign->tokens()->delete();

        return [
            'revoked' => $count,
            'message' => "Revoked {$count} token(s)",
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
