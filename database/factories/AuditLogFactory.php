<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'auditable_type' => fake()->randomElement([
                \App\Models\Campaign::class,
                \App\Models\Document::class,
                \App\Models\Credential::class,
            ]),
            'auditable_id' => fake()->uuid(),
            'event' => fake()->randomElement(['created', 'updated', 'deleted', 'published', 'archived']),
            'old_values' => null,
            'new_values' => [
                'status' => 'active',
            ],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'tags' => [fake()->word()],
        ];
    }

    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'created',
            'old_values' => null,
        ]);
    }

    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'updated',
            'old_values' => [
                'status' => 'draft',
            ],
            'new_values' => [
                'status' => 'active',
            ],
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'deleted',
            'new_values' => null,
        ]);
    }
}
