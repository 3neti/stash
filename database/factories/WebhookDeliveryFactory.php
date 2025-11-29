<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => \App\Models\Campaign::factory(),
            'event_type' => fake()->randomElement(['document.processing.completed', 'document.processing.failed']),
            'payload' => [
                'event' => 'document.processing.completed',
                'timestamp' => now()->toISOString(),
                'data' => [
                    'document' => ['id' => (string) \Symfony\Component\Uid\Ulid::generate()],
                ],
            ],
            'response_status' => fake()->randomElement([200, 201, null]),
            'response_body' => fake()->randomElement(['OK', null]),
            'attempted_at' => now(),
            'delivered_at' => fake()->boolean(70) ? now() : null,
            'failed_at' => fake()->boolean(10) ? now() : null,
            'attempts' => fake()->numberBetween(1, 3),
        ];
    }
}
