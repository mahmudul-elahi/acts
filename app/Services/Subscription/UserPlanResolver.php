<?php

namespace App\Services\Subscription;

use App\Enums\BillingPeriod;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class UserPlanResolver
{
    /**
     * Label used when a user has no active or lifetime plan.
     */
    public const FREE = 'Free';

    /**
     * Attach a `subscription_label` attribute to each user describing the plan
     * they are currently on (e.g. "Monthly", "Yearly", "Lifetime", "Free").
     *
     * @param  EloquentCollection<int, User>  $users
     */
    public function attachLabels(EloquentCollection $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $users->loadMissing('subscriptions');

        $labelsByPrice = $this->planLabelsByPrice();
        $lifetimeUserIds = $this->lifetimeUserIds($users->modelKeys());

        $users->each(function (User $user) use ($labelsByPrice, $lifetimeUserIds): void {
            $user->setAttribute('subscription_label', $this->labelFor($user, $labelsByPrice, $lifetimeUserIds));
        });
    }

    /**
     * Resolve the plan label for a single user from preloaded lookups.
     *
     * @param  Collection<string, string>  $labelsByPrice
     * @param  Collection<int|string, int>  $lifetimeUserIds
     */
    private function labelFor(User $user, Collection $labelsByPrice, Collection $lifetimeUserIds): string
    {
        $activePrice = $user->subscriptions->first(fn ($subscription) => $subscription->valid())?->stripe_price;

        if ($activePrice && $labelsByPrice->has($activePrice)) {
            return $labelsByPrice->get($activePrice);
        }

        return $lifetimeUserIds->has($user->getKey()) ? BillingPeriod::OnePayment->planName() : self::FREE;
    }

    /**
     * Map each Stripe price id to its plan's billing-period label.
     *
     * @return Collection<string, string>
     */
    private function planLabelsByPrice(): Collection
    {
        return SubscriptionPlan::query()
            ->whereNotNull('stripe_price_id')
            ->get(['stripe_price_id', 'billing_period'])
            ->mapWithKeys(fn (SubscriptionPlan $plan): array => [$plan->stripe_price_id => $plan->billing_period->planName()]);
    }

    /**
     * Resolve which of the given users have a successful one-time (lifetime) payment.
     *
     * @param  array<int, int|string>  $userIds
     * @return Collection<int|string, int>
     */
    private function lifetimeUserIds(array $userIds): Collection
    {
        $lifetimePlanIds = SubscriptionPlan::query()
            ->where('billing_period', BillingPeriod::OnePayment->value)
            ->pluck('id');

        if ($lifetimePlanIds->isEmpty()) {
            return collect();
        }

        return Payment::query()
            ->succeeded()
            ->whereIn('user_id', $userIds)
            ->whereIn('subscription_plan_id', $lifetimePlanIds)
            ->pluck('user_id')
            ->flip();
    }
}
