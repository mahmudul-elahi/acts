<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
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
            'quote' => fake()->sentence(),
            'author' => fake()->randomElement(['The Dig', 'Ancient Wisdom', fake()->name()]),
            'status' => true,
            'reaction_count' => fake()->numberBetween(0, 2000),
            'notes' => fake()->boolean() ? fake()->sentence(3) : null,
        ];
    }

    /**
     * Indicate that the quote is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
        ]);
    }
}
