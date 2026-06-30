<?php

namespace Database\Factories;

use App\Enums\JournalType;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => JournalType::Text,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'media_path' => null,
            'media_mime' => null,
            'status' => true,
        ];
    }

    /**
     * Indicate an image (or short video) entry.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => JournalType::Image,
            'media_path' => 'journals/'.fake()->uuid().'.jpg',
            'media_mime' => 'image/jpeg',
        ]);
    }

    /**
     * Indicate an audio entry (premium).
     */
    public function audio(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => JournalType::Audio,
            'media_path' => 'journals/'.fake()->uuid().'.mp3',
            'media_mime' => 'audio/mpeg',
        ]);
    }

    /**
     * Indicate the entry has been deactivated by an admin.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
        ]);
    }
}
