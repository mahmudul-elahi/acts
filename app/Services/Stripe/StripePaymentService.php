<?php

namespace App\Services\Stripe;

use Laravel\Cashier\Cashier;
use Stripe\StripeClient;
use Throwable;

/**
 * Thin wrapper around the Stripe SDK for reading payment-related data
 * that is not present in webhook payloads (e.g. card details on a charge).
 */
class StripePaymentService
{
    /**
     * Whether a Stripe secret key is configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('cashier.secret'));
    }

    /**
     * Resolve the card brand and last four digits for a charge.
     *
     * Returns null values when Stripe is not configured, no charge is given,
     * or the lookup fails — card details are best-effort.
     *
     * @return array{brand: string|null, last_four: string|null}
     */
    public function cardForCharge(?string $chargeId): array
    {
        $empty = ['brand' => null, 'last_four' => null];

        if (! $chargeId || ! $this->isConfigured()) {
            return $empty;
        }

        try {
            $card = $this->stripe()->charges->retrieve($chargeId)->payment_method_details?->card;

            return [
                'brand' => $card?->brand,
                'last_four' => $card?->last4,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * Resolve the card brand and last four digits for a payment intent.
     *
     * Used for one-time Checkout payments, where only the payment intent id is
     * present in the webhook payload. Card details are best-effort.
     *
     * @return array{brand: string|null, last_four: string|null}
     */
    public function cardForPaymentIntent(?string $paymentIntentId): array
    {
        $empty = ['brand' => null, 'last_four' => null];

        if (! $paymentIntentId || ! $this->isConfigured()) {
            return $empty;
        }

        try {
            $charge = $this->stripe()->paymentIntents->retrieve(
                $paymentIntentId,
                ['expand' => ['latest_charge']],
            )->latest_charge;

            return $this->cardForCharge(is_string($charge) ? $charge : $charge?->id);
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * Resolve the underlying Stripe client.
     */
    protected function stripe(): StripeClient
    {
        return Cashier::stripe();
    }
}
