<?php

namespace App\Services\Stripe;

use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

/**
 * Thin wrapper around the Stripe SDK for managing the plan catalog
 * (Stripe Products and Prices). Customer billing lives elsewhere.
 */
class StripePlanService
{
    /**
     * Whether a Stripe secret key is configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('cashier.secret'));
    }

    /**
     * Create a Stripe product and return its identifier.
     */
    public function createProduct(string $name, ?string $description = null): string
    {
        return $this->stripe()->products->create(array_filter([
            'name' => $name,
            'description' => $description,
        ]))->id;
    }

    /**
     * Update a Stripe product's name and description.
     */
    public function updateProduct(string $productId, string $name, ?string $description = null): void
    {
        $this->stripe()->products->update($productId, [
            'name' => $name,
            'description' => $description ?? '',
        ]);
    }

    /**
     * Create a price for a product and return its identifier.
     *
     * A null interval produces a one-time price; otherwise a recurring price.
     */
    public function createPrice(string $productId, int $unitAmount, string $currency, ?string $interval = null): string
    {
        $payload = [
            'product' => $productId,
            'unit_amount' => $unitAmount,
            'currency' => $currency,
        ];

        if ($interval !== null) {
            $payload['recurring'] = ['interval' => $interval];
        }

        return $this->stripe()->prices->create($payload)->id;
    }

    /**
     * Archive a price so it can no longer be used for new purchases.
     */
    public function archivePrice(string $priceId): void
    {
        $this->stripe()->prices->update($priceId, ['active' => false]);
    }

    /**
     * Archive a product so it no longer appears in the catalog.
     */
    public function archiveProduct(string $productId): void
    {
        $this->stripe()->products->update($productId, ['active' => false]);
    }

    /**
     * Resolve the underlying Stripe client.
     */
    protected function stripe(): StripeClient
    {
        return Cashier::stripe();
    }
}
