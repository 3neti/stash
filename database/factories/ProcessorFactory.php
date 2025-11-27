<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Processor>
 */
class ProcessorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'class_name' => 'App\\Processors\\' . fake()->word() . 'Processor',
            'category' => fake()->randomElement(['ocr', 'classification', 'extraction', 'validation', 'enrichment', 'notification', 'storage', 'custom']),
            'description' => fake()->sentence(),
            'config_schema' => [
                'type' => 'object',
                'properties' => [
                    'enabled' => ['type' => 'boolean'],
                ],
            ],
            'is_system' => false,
            'is_active' => true,
            'version' => fake()->randomElement(['1.0.0', '1.1.0', '2.0.0']),
            'author' => fake()->name(),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function ocr(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'ocr',
            'name' => 'OCR Processor',
            'slug' => 'ocr-processor',
        ]);
    }
}
