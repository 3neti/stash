<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'status' => fake()->randomElement(['draft', 'active', 'paused', 'archived']),
            'type' => fake()->randomElement(['template', 'custom', 'meta']),
            'pipeline_config' => [
                'processors' => [
                    ['type' => 'ocr', 'enabled' => true],
                    ['type' => 'classification', 'enabled' => true],
                ],
            ],
            'checklist_template' => [
                ['item' => 'Review document', 'required' => true],
                ['item' => 'Verify signatures', 'required' => false],
            ],
            'settings' => [
                'queue' => 'default',
                'ai_provider' => 'openai',
            ],
            'max_concurrent_jobs' => fake()->numberBetween(5, 20),
            'retention_days' => fake()->randomElement([30, 60, 90, 180]),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }
}
