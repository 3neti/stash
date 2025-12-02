<?php

declare(strict_types=1);

namespace App\Actions\Campaigns\Web;

use App\Models\Campaign;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Update existing campaign.
 */
class UpdateCampaign
{
    use AsAction;

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', 'required', 'in:template,custom,meta'],
            'state' => ['sometimes', 'required', 'in:draft,active,paused,archived'],
            'pipeline_config' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * Update campaign.
     */
    public function handle(Campaign $campaign, array $data): Campaign
    {
        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $campaign->update($data);

        return $campaign->fresh();
    }

    /**
     * Handle as controller.
     */
    public function asController(ActionRequest $request, Campaign $campaign): Campaign
    {
        return $this->handle($campaign, $request->validated());
    }
}
