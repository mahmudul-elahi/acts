<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'badge_name' => fake()->randomElement([null, 'Popular', 'On Sale', 'Special']),
            'title' => fake()->randomElement(['Monthly', 'Annual', 'Lifetime', 'Starter', 'Pro']),
            'sub_title' => fake()->sentence(4),
            'description' => fake()->optional()->sentence(10),
            'price' => fake()->randomFloat(2, 5, 200),
            'currency' => 'usd',
            'billing_period' => fake()->randomElement(BillingPeriod::cases()),
            'features' => fake()->sentences(fake()->numberBetween(2, 5)),
            'stripe_product_id' => null,
            'stripe_price_id' => null,
            'status' => true,
        ];
    }

    /**
     * Indicate a monthly recurring plan.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Monthly',
            'billing_period' => BillingPeriod::Monthly,
        ]);
    }

    /**
     * Indicate a yearly recurring plan.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Annual',
            'billing_period' => BillingPeriod::Yearly,
        ]);
    }

    /**
     * Indicate a one-time lifetime plan.
     */
    public function lifetime(): static
    {
        return $this->state(fn (array $attributes): array => [
            'title' => 'Lifetime',
            'billing_period' => BillingPeriod::OnePayment,
        ]);
    }

    /**
     * Indicate the plan is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
        ]);
    }

    /**
     * Indicate the plan has been mirrored to Stripe.
     */
    public function syncedToStripe(): static
    {
        return $this->state(fn (array $attributes): array => [
            'stripe_product_id' => 'prod_'.fake()->bothify('??????????'),
            'stripe_price_id' => 'price_'.fake()->bothify('??????????'),
        ]);
    }
}
