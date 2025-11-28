<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\States\Document\PendingDocumentState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
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
            'campaign_id' => Campaign::factory(),
            'user_id' => null,
            'original_filename' => fake()->word() . '.' . fake()->fileExtension(),
            'mime_type' => fake()->mimeType(),
            'size_bytes' => fake()->numberBetween(1024, 10485760),
            'storage_path' => 'documents/' . fake()->uuid() . '.pdf',
            'storage_disk' => 's3',
            'hash' => hash('sha256', fake()->uuid()),
            'state' => PendingDocumentState::$name,
            'metadata' => [
                'pages' => fake()->numberBetween(1, 50),
                'language' => 'en',
            ],
            'processing_history' => [],
            'retry_count' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'pending',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'completed',
            'processed_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'failed',
            'error_message' => fake()->sentence(),
            'failed_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'state' => 'processing',
        ]);
    }
}
