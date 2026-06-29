<?php

namespace Database\Factories;

use App\Models\MurmurationComment;
use App\Models\MurmurationPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MurmurationComment>
 */
class MurmurationCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'murmuration_post_id' => MurmurationPost::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => fake()->sentence(),
        ];
    }

    /**
     * Make this comment a reply to the given parent comment.
     */
    public function replyTo(MurmurationComment $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'murmuration_post_id' => $parent->murmuration_post_id,
            'parent_id' => $parent->id,
        ]);
    }
}
