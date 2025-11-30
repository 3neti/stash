<?php

declare(strict_types=1);

namespace App\Actions\Campaigns\Web;

use App\Models\Campaign;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Create new campaign.
 */
class CreateCampaign
{
    use AsAction;

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:template,custom,meta'],
            'pipeline_config' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * Create campaign.
     */
    public function handle(array $data): Campaign
    {
        return Campaign::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'status' => 'draft',
            'pipeline_config' => $data['pipeline_config'] ?? [],
            'settings' => $data['settings'] ?? [],
        ]);
    }

    /**
     * Handle as controller.
     */
    public function asController(ActionRequest $request): Campaign
    {
        return $this->handle($request->validated());
    }
}
