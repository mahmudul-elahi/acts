<?php

namespace App\Enums;

enum BillingPeriod: string
{
    case Monthly = 'month';
    case Yearly = 'year';
    case OnePayment = 'one_payment';

    /**
     * Human readable label for the billing period.
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
