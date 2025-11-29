<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Set campaign notification channel (webhook, mobile, email).
 */
class SetCampaignChannel
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
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'in:webhook,mobile,email'],
            'value' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Set campaign channel.
     */
    public function handle(Campaign $campaign, string $channel, string $value): array
    {
        // Use HasChannels trait magic setter
        $campaign->setChannel($channel, $value);

        return [
            'channel' => $channel,
            'value' => $value,
            'message' => "Channel '{$channel}' set successfully",
        ];
    }

    /**
     * Handle as controller.
     */
    public function asController(ActionRequest $request, Campaign $campaign): array
    {
        return $this->handle(
            $campaign,
            $request->validated('channel'),
            $request->validated('value')
        );
    }
}
