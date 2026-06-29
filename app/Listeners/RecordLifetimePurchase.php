<?php

namespace App\Listeners;

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Stripe\StripePaymentService;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Grants lifetime access and records the payment for the one-time "Lifetime"
 * plan. Recurring subscriptions are handled by {@see RecordStripePayment} via
 * their paid invoices; one-time Checkout payments produce no invoice, so they
 * are fulfilled from the completed Checkout session instead.
 */
class RecordLifetimePurchase
{
    public function __construct(private StripePaymentService $stripe) {}

    /**
     * Fulfil a completed one-time Checkout session.
     */
    public function handle(WebhookReceived $event): void
    {
        if (($event->payload['type'] ?? null) !== 'checkout.session.completed') {
            return;
        }

        $session = $event->payload['data']['object'] ?? [];

        if (($session['mode'] ?? null) !== 'payment') {
            return;
        }

        $user = Cashier::findBillable($session['customer'] ?? null);

        if (! $user instanceof User) {
            return;
        }

        $user->forceFill(['lifetime_access' => true])->save();

        $plan = $this->resolvePlan($session['metadata'] ?? []);
        $card = $this->stripe->cardForPaymentIntent($session['payment_intent'] ?? null);
        $paidAt = $session['created'] ?? null;

        Payment::updateOrCreate(
            ['stripe_id' => (string) ($session['payment_intent'] ?? $session['id'])],
            [
                'user_id' => $user->getKey(),
                'subscription_plan_id' => $plan?->id,
                'type' => 'one_time',
                'amount' => $session['amount_total'] ?? 0,
                'currency' => $session['currency'] ?? config('cashier.currency', 'usd'),
                'card_brand' => $card['brand'],
                'card_last_four' => $card['last_four'],
                'status' => 'succeeded',
                'paid_at' => $paidAt ? Carbon::createFromTimestamp($paidAt) : Carbon::now(),
            ],
        );
    }

    /**
     * Resolve the local plan referenced in the Checkout session metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function resolvePlan(array $metadata): ?SubscriptionPlan
    {
        return isset($metadata['subscription_plan_id'])
            ? SubscriptionPlan::find($metadata['subscription_plan_id'])
            : null;
    }
}
