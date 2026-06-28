<?php

namespace Database\Factories;

use App\Models\Ad;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ad>
 */
class AdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publishDate = fake()->dateTimeBetween('-2 months', '+1 month');

        return [
            'title' => fake()->randomElement(['Save 15% - Fitness Starter', '20% Off - Organic Meals', '30% Off - Yoga Equipment', 'Flash Sale - 40% Off', 'Buy One Get One - Skincare', 'Exclusive 25% Discount']),
            'description' => fake()->sentence(8),
            'image' => null,
            'link' => 'https://yoursite.com/landing-page',
            'coupon_code' => Str::upper(Str::random(8)),
            'publish_date' => $publishDate,
            'expiration_date' => fake()->dateTimeBetween($publishDate, '+6 months'),
            'status' => true,
        ];
    }

    /**
     * Indicate that the ad is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
        ]);
    }
}
