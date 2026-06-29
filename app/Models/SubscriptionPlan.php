<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['badge_name', 'title', 'sub_title', 'description', 'price', 'currency', 'billing_period', 'trial_days', 'features', 'stripe_product_id', 'stripe_price_id', 'status'])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'billing_period' => BillingPeriod::class,
            'trial_days' => 'integer',
            'status' => 'boolean',
        ];
    }

    /**
     * The price expressed in the smallest currency unit (e.g. cents) for Stripe.
     */
    public function unitAmount(): int
    {
        return (int) round((float) $this->price * 100);
    }

    /**
     * Whether this plan offers a free trial. Stripe Checkout requires trials of
     * at least 48 hours, and only recurring plans can be trialed.
     */
    public function hasTrial(): bool
    {
        return $this->billing_period->isRecurring() && (int) $this->trial_days >= 2;
    }

    /**
     * Limit results by billing period (month, year, one_payment).
     */
    #[Scope]
    protected function billingPeriod(Builder $query, string $value): void
    {
        $query->where('billing_period', $value);
    }

    /**
     * Limit results by run state. Anything other than "active"/"inactive" applies no filter.
     */
    #[Scope]
    protected function status(Builder $query, string $value): void
    {
        match ($value) {
            'active' => $query->where('status', true),
            'inactive' => $query->where('status', false),
            default => $query,
        };
    }
}
