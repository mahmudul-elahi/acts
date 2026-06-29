<?php

namespace Database\Factories;

use App\Enums\MurmurationPostType;
use App\Models\MurmurationPost;
use App\Models\MurmurationTopic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MurmurationPost>
 */
class MurmurationPostFactory extends Factory
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
            'murmuration_topic_id' => MurmurationTopic::factory(),
            'type' => MurmurationPostType::Text,
            'body' => fake()->paragraph(),
            'media_path' => null,
            'media_mime' => null,
            'status' => true,
        ];
    }

    /**
     * Indicate an image (or short video) post.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MurmurationPostType::Image,
            'media_path' => 'murmuration/'.fake()->uuid().'.jpg',
            'media_mime' => 'image/jpeg',
        ]);
    }

    /**
     * Indicate an audio post (premium).
     */
    public function audio(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => MurmurationPostType::Audio,
            'media_path' => 'murmuration/'.fake()->uuid().'.mp3',
            'media_mime' => 'audio/mpeg',
        ]);
    }

    /**
     * Indicate the post has been deactivated by an admin.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
        ]);
    }
}
