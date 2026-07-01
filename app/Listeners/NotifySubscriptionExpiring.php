<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Alerts a member when their subscription is scheduled to expire, i.e. when
 * Stripe reports the subscription was just set to cancel at period end (from
 * the app's cancel flow or the Stripe dashboard). The alert names the date the
 * access actually ends.
 */
class NotifySubscriptionExpiring
{
    /**
     * Notify the billable user when a subscription becomes cancel-at-period-end.
     */
    public function handle(WebhookReceived $event): void
    {
        if (($event->payload['type'] ?? null) !== 'customer.subscription.updated') {
            return;
        }

        $object = $event->payload['data']['object'] ?? [];
        $previous = $event->payload['data']['previous_attributes'] ?? [];

        if (! $this->justScheduledToCancel($object, $previous)) {
            return;
        }

        $user = Cashier::findBillable($object['customer'] ?? null);

        if (! $user instanceof User || ! $user->wantsSubscriptionAlerts()) {
            return;
        }

        $timestamp = $object['cancel_at'] ?? $object['current_period_end'] ?? null;

        if (! $timestamp) {
            return;
        }

        $expiresAt = Carbon::createFromTimestamp($timestamp);

        if ($this->alreadyNotified($user, $expiresAt)) {
            return;
        }

        $user->notify(new SubscriptionExpiringNotification($expiresAt));
    }

    /**
     * Whether this update is the moment the subscription flipped to cancel at
     * period end, rather than any other subscription change.
     *
     * @param  array<string, mixed>  $object
     * @param  array<string, mixed>  $previous
     */
    private function justScheduledToCancel(array $object, array $previous): bool
    {
        return ($object['cancel_at_period_end'] ?? false) === true
            && array_key_exists('cancel_at_period_end', $previous)
            && ! ($previous['cancel_at_period_end'] ?? false);
    }

    /**
     * Whether the user already has an expiry alert for this exact end date,
     * so a redelivered webhook does not notify twice.
     */
    private function alreadyNotified(User $user, Carbon $expiresAt): bool
    {
        return $user->notifications()
            ->where('type', 'subscription_expiring')
            ->get()
            ->contains(fn ($notification): bool => ($notification->data['expires_at'] ?? null) === $expiresAt->toISOString());
    }
}
