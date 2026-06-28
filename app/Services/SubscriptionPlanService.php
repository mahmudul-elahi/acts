<?php

namespace App\Services;

use App\Enums\BillingPeriod;
use App\Models\SubscriptionPlan;
use App\Services\Stripe\StripePlanService;

class SubscriptionPlanService
{
    public function __construct(private StripePlanService $stripe) {}

    /**
     * Create a subscription plan and mirror it to Stripe as a product + price.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SubscriptionPlan
    {
        if ($this->stripe->isConfigured()) {
            $productId = $this->stripe->createProduct($data['title'], $data['description'] ?? null);

            $data['stripe_product_id'] = $productId;
            $data['stripe_price_id'] = $this->stripe->createPrice(
                $productId,
                $this->unitAmount($data['price']),
                $data['currency'],
                $this->intervalFor($data['billing_period']),
            );
        }

        return SubscriptionPlan::create($data)->refresh();
    }

    /**
     * Update a plan, keeping its Stripe product and price in sync.
     *
     * Stripe prices are immutable, so any pricing change creates a new price
     * and archives the previous one.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        if ($this->stripe->isConfigured() && $plan->stripe_product_id) {
            $this->stripe->updateProduct(
                $plan->stripe_product_id,
                $data['title'] ?? $plan->title,
                array_key_exists('description', $data) ? $data['description'] : $plan->description,
            );

            if ($this->pricingChanged($plan, $data)) {
                $previousPriceId = $plan->stripe_price_id;

                $data['stripe_price_id'] = $this->stripe->createPrice(
                    $plan->stripe_product_id,
                    $this->unitAmount($data['price'] ?? $plan->price),
                    $data['currency'] ?? $plan->currency,
                    $this->intervalFor($data['billing_period'] ?? $plan->billing_period),
                );

                if ($previousPriceId) {
                    $this->stripe->archivePrice($previousPriceId);
                }
            }
        }

        $plan->update($data);

        return $plan;
    }

    /**
     * Delete a plan and archive its Stripe product and price.
     */
    public function delete(SubscriptionPlan $plan): void
    {
        if ($this->stripe->isConfigured()) {
            if ($plan->stripe_price_id) {
                $this->stripe->archivePrice($plan->stripe_price_id);
            }

            if ($plan->stripe_product_id) {
                $this->stripe->archiveProduct($plan->stripe_product_id);
            }
        }

        $plan->delete();
    }

    /**
     * Determine whether a pricing-related attribute changed.
     *
     * @param  array<string, mixed>  $data
     */
    private function pricingChanged(SubscriptionPlan $plan, array $data): bool
    {
        return (isset($data['price']) && (float) $data['price'] !== (float) $plan->price)
            || (isset($data['currency']) && $data['currency'] !== $plan->currency)
            || (isset($data['billing_period']) && $this->intervalFor($data['billing_period']) !== $this->intervalFor($plan->billing_period));
    }

    /**
     * Convert a decimal price into the smallest currency unit (e.g. cents).
     */
    private function unitAmount(int|float|string $price): int
    {
        return (int) round((float) $price * 100);
    }

    /**
     * Resolve the Stripe recurring interval for a billing period value.
     */
    private function intervalFor(BillingPeriod|string $period): ?string
    {
        return ($period instanceof BillingPeriod ? $period : BillingPeriod::from($period))->stripeInterval();
    }
}
