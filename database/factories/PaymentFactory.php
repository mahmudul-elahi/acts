<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'stripe_id' => 'in_'.fake()->unique()->bothify('??????????'),
            'type' => 'subscription',
            'amount' => fake()->numberBetween(500, 20000),
            'currency' => 'usd',
            'card_brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'card_last_four' => (string) fake()->numberBetween(1000, 9999),
            'status' => 'succeeded',
            'paid_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }

    /**
     * Indicate a failed payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }
}
