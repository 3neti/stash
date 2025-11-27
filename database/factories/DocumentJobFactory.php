<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentJob>
 */
class DocumentJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'campaign_id' => null,
            'document_id' => null,
            'pipeline_instance' => [
                'current_step' => 0,
                'total_steps' => 3,
            ],
            'current_processor_index' => 0,
            'status' => fake()->randomElement(['pending', 'queued', 'running', 'completed', 'failed']),
            'queue_name' => 'default',
            'attempts' => 0,
            'max_attempts' => 3,
            'error_log' => [],
        ];
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => fake()->dateTimeBetween('-2 hours', '-1 hour'),
            'completed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'attempts' => 3,
            'error_log' => [
                [
                    'timestamp' => now()->toIso8601String(),
                    'attempt' => 3,
                    'error' => fake()->sentence(),
                ],
            ],
            'failed_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }
}
