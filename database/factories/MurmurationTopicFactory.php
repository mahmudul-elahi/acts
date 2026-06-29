<?php

namespace Database\Factories;

use App\Models\MurmurationTopic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MurmurationTopic>
 */
class MurmurationTopicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(fake()->numberBetween(1, 3), true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => true,
        ];
    }

    /**
     * Indicate the topic is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
        ]);
    }
}
