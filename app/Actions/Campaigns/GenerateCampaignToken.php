<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Generate API token for campaign.
 * 
 * Revokes existing tokens and creates a new one.
 * Returns plain-text token only once.
 */
class GenerateCampaignToken
{
    use AsAction;

    /**
     * Authorize the action.
     */
    public function authorize(ActionRequest $request): bool
    {
        // Only authenticated users can generate tokens
        return $request->user() !== null;
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'in:read,write,admin'],
        ];
    }

    /**
     * Generate campaign API token.
     */
    public function handle(Campaign $campaign, ?string $name = null, array $abilities = ['*']): array
    {
        // Revoke existing tokens
        $campaign->tokens()->delete();

        // Generate new token
        $tokenName = $name ?? 'API Token';
        $token = $campaign->createToken($tokenName, $abilities);

        return [
            'token' => $token->plainTextToken,
            'name' => $tokenName,
            'abilities' => $abilities,
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Handle as controller.
     */
    public function asController(ActionRequest $request, Campaign $campaign): array
    {
        return $this->handle(
            $campaign,
            $request->validated('name'),
            $request->validated('abilities', ['*'])
        );
    }
}
