<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Credential>
 */
class CredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'credentialable_type' => null,  // System-level by default
            'credentialable_id' => null,
            'key' => fake()->randomElement(['openai_api_key', 'anthropic_api_key', 'aws_access_key', 'smtp_password']),
            'value' => 'test-credential-' . fake()->uuid(),
            'provider' => fake()->randomElement(['openai', 'anthropic', 'aws', 'smtp']),
            'metadata' => [
                'description' => fake()->sentence(),
            ],
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'credentialable_type' => null,
            'credentialable_id' => null,
        ]);
    }

    public function forCampaign(\App\Models\Campaign $campaign): static
    {
        return $this->for($campaign, 'credentialable');
    }

    public function forProcessor(\App\Models\Processor $processor): static
    {
        return $this->for($processor, 'credentialable');
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}
