<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UsageEvent>
 */
class UsageEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => null,
            'document_id' => null,
            'job_id' => null,
            'event_type' => fake()->randomElement(['upload', 'storage', 'processor_execution', 'ai_task', 'connector_call', 'agent_tool']),
            'units' => fake()->numberBetween(1, 100),
            'cost_credits' => fake()->numberBetween(1, 50),
            'metadata' => [
                'source' => fake()->word(),
            ],
            'recorded_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function upload(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'upload',
            'units' => 1,
            'cost_credits' => 1,
        ]);
    }

    public function aiTask(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'ai_task',
            'units' => fake()->numberBetween(100, 5000),
            'cost_credits' => fake()->numberBetween(5, 100),
            'metadata' => [
                'model' => 'gpt-4',
                'tokens' => fake()->numberBetween(100, 5000),
            ],
        ]);
    }

    public function processorExecution(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'processor_execution',
            'units' => 1,
            'cost_credits' => fake()->numberBetween(1, 10),
        ]);
    }
}
