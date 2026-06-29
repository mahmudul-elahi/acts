<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Subscription;

class SubscriptionService
{
    /**
     * The name Cashier uses for the user's single subscription.
     */
    public const SUBSCRIPTION_NAME = 'default';

    /**
     * Get the active subscription plans, cheapest first.
     *
     * @return Collection<int, SubscriptionPlan>
     */
    public function activePlans(): Collection
    {
        return SubscriptionPlan::query()
            ->where('status', true)
            ->orderBy('price')
            ->get();
    }

    /**
     * Start a Stripe Checkout session for the given plan and return it.
     *
     * Recurring plans create a subscription checkout; the one-time "Lifetime"
     * plan creates a single-charge checkout.
     */
    public function checkout(User $user, SubscriptionPlan $plan, bool $withTrial): Checkout
    {
        $options = [
            'success_url' => route('subscription.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('subscription.cancel'),
            'metadata' => ['subscription_plan_id' => $plan->id],
        ];

        if (! $plan->billing_period->isRecurring()) {
            return $user->checkout([$plan->stripe_price_id => 1], $options);
        }

        $builder = $user->newSubscription(self::SUBSCRIPTION_NAME, $plan->stripe_price_id);

        if ($withTrial && $plan->hasTrial()) {
            $builder->trialDays((int) $plan->trial_days);
        }

        return $builder->checkout($options);
    }

    /**
     * Build the user's current subscription status.
     *
     * @return array{
     *     is_premium: bool,
     *     access_type: string,
     *     status: string,
     *     on_trial: bool,
     *     trial_ends_at: \Illuminate\Support\Carbon|null,
     *     on_grace_period: bool,
     *     ends_at: \Illuminate\Support\Carbon|null,
     *     plan: SubscriptionPlan|null,
     * }
     */
    public function status(User $user): array
    {
        $subscription = $user->subscription(self::SUBSCRIPTION_NAME);

        return [
            'is_premium' => $user->hasPremiumAccess(),
            'access_type' => $this->accessType($user, $subscription),
            'status' => $this->statusLabel($user, $subscription),
            'on_trial' => (bool) $subscription?->onTrial(),
            'trial_ends_at' => $subscription?->trial_ends_at,
            'on_grace_period' => (bool) $subscription?->onGracePeriod(),
            'ends_at' => $subscription?->ends_at,
            'plan' => $this->currentPlan($user, $subscription),
        ];
    }

    /**
     * Cancel the user's subscription at the end of the billing period.
     */
    public function cancel(User $user): bool
    {
        $subscription = $user->subscription(self::SUBSCRIPTION_NAME);

        if (! $subscription || $subscription->canceled()) {
            return false;
        }

        $subscription->cancel();

        return true;
    }

    /**
     * Resume a subscription that is still within its grace period.
     */
    public function resume(User $user): bool
    {
        $subscription = $user->subscription(self::SUBSCRIPTION_NAME);

        if (! $subscription || ! $subscription->onGracePeriod()) {
            return false;
        }

        $subscription->resume();

        return true;
    }

    /**
     * Resolve the high-level access type for the status payload.
     */
    private function accessType(User $user, ?Subscription $subscription): string
    {
        if ($user->lifetime_access) {
            return 'lifetime';
        }

        if ($subscription && $subscription->valid()) {
            return $this->currentPlan($user, $subscription)?->billing_period->value === 'year'
                ? 'yearly'
                : 'monthly';
        }

        return 'none';
    }

    /**
     * Resolve a human-facing status string.
     */
    private function statusLabel(User $user, ?Subscription $subscription): string
    {
        if ($subscription) {
            return $subscription->stripe_status;
        }

        return $user->lifetime_access ? 'lifetime' : 'none';
    }

    /**
     * Resolve the plan backing the user's current access, if any.
     */
    private function currentPlan(User $user, ?Subscription $subscription): ?SubscriptionPlan
    {
        if ($subscription && $subscription->stripe_price) {
            return SubscriptionPlan::query()
                ->where('stripe_price_id', $subscription->stripe_price)
                ->first();
        }

        if ($user->lifetime_access) {
            return $user->payments()
                ->whereHas('plan', fn ($query) => $query->where('billing_period', 'one_payment'))
                ->latest('paid_at')
                ->first()?->plan;
        }

        return null;
    }
}
