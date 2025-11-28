<?php

namespace Database\Factories;

use App\Models\DocumentJob;
use App\Models\Processor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessorExecution>
 */
class ProcessorExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => DocumentJob::factory(),
            'processor_id' => Processor::factory(),
            'input_data' => [
                'document_path' => 'documents/sample.pdf',
            ],
            'output_data' => null,
            'config' => [
                'enabled' => true,
            ],
            'duration_ms' => null,
            'tokens_used' => 0,
            'cost_credits' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'completed',
            'output_data' => [
                'text' => fake()->paragraph(),
                'confidence' => fake()->randomFloat(2, 0.8, 1.0),
            ],
            'duration_ms' => fake()->numberBetween(100, 5000),
            'tokens_used' => fake()->numberBetween(50, 2000),
            'cost_credits' => fake()->numberBetween(1, 50),
            'started_at' => fake()->dateTimeBetween('-10 minutes', '-5 minutes'),
            'completed_at' => fake()->dateTimeBetween('-5 minutes', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'failed',
            'error_message' => fake()->sentence(),
            'duration_ms' => fake()->numberBetween(100, 1000),
        ]);
    }
}
