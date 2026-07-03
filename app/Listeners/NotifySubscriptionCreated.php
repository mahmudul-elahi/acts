<?php

namespace App\Listeners;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Notifications\SubscriptionPurchasedNotification;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class NotifySubscriptionCreated
{
    public function handle(WebhookReceived $event): void
    {
        if (($event->payload['type'] ?? null) !== 'customer.subscription.created') {
            return;
        }

        $object = $event->payload['data']['object'] ?? [];
        $user = Cashier::findBillable($object['customer'] ?? null);

        if (! $user instanceof User || ! $user->wantsSubscriptionAlerts()) {
            return;
        }

        $priceId = $object['items']['data'][0]['price']['id'] ?? null;

        if (! $priceId) {
            return;
        }

        $plan = SubscriptionPlan::where('stripe_price_id', $priceId)->first();

        if (! $plan) {
            return;
        }

        $user->notify(new SubscriptionPurchasedNotification($plan));
    }
}
