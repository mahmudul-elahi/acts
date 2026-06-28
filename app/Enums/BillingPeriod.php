<?php

namespace App\Enums;

enum BillingPeriod: string
{
    case Monthly = 'month';
    case Yearly = 'year';
    case OnePayment = 'one_payment';

    /**
     * Human readable label for the billing period (as shown on the plan form).
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Month',
            self::Yearly => 'Year',
            self::OnePayment => 'One Payment',
        };
    }

    /**
     * Plan name as shown in subscription and payment listings.
     */
    public function planName(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
            self::OnePayment => 'Lifetime',
        };
    }

    /**
     * The Stripe recurring interval, or null for a one-time price.
     */
    public function stripeInterval(): ?string
    {
        return match ($this) {
            self::Monthly => 'month',
            self::Yearly => 'year',
            self::OnePayment => null,
        };
    }

    /**
     * Whether this period maps to a recurring Stripe price.
     */
    public function isRecurring(): bool
    {
        return $this->stripeInterval() !== null;
    }
}
