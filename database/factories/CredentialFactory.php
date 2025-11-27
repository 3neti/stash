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
            'scope_type' => fake()->randomElement(['system', 'subscriber', 'campaign', 'processor']),
            'scope_id' => null,
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
            'scope_type' => 'system',
            'scope_id' => null,
        ]);
    }

    public function subscriber(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => 'subscriber',
        ]);
    }

    public function campaign(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => 'campaign',
        ]);
    }

    public function processor(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope_type' => 'processor',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }
}
