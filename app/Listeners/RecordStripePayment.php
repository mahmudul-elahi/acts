<?php

namespace App\Listeners;

use App\Enums\BillingPeriod;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\Stripe\StripePaymentService;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class RecordStripePayment
{
    /**
     * The webhook events this listener records.
     */
    private const HANDLED = [
        'invoice.payment_succeeded' => 'succeeded',
        'invoice.payment_failed' => 'failed',
    ];

    public function __construct(private StripePaymentService $stripe) {}

    /**
     * Record a local payment when Stripe reports an invoice outcome.
     */
    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! array_key_exists($type, self::HANDLED)) {
            return;
        }

        $invoice = $event->payload['data']['object'] ?? [];
        $stripeId = $invoice['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        $status = self::HANDLED[$type];
        $plan = $this->resolvePlan($invoice);
        $card = $this->stripe->cardForCharge($invoice['charge'] ?? null);
        $paidAt = $invoice['status_transitions']['paid_at'] ?? $invoice['created'] ?? null;

        Payment::updateOrCreate(
            ['stripe_id' => $stripeId],
            [
                'user_id' => Cashier::findBillable($invoice['customer'] ?? null)?->getKey(),
                'subscription_plan_id' => $plan?->id,
                'type' => $plan?->billing_period === BillingPeriod::OnePayment ? 'one_time' : 'subscription',
                'amount' => $invoice['amount_paid'] ?? $invoice['amount_due'] ?? 0,
                'currency' => $invoice['currency'] ?? config('cashier.currency', 'usd'),
                'card_brand' => $card['brand'],
                'card_last_four' => $card['last_four'],
                'status' => $status,
                'paid_at' => $status === 'succeeded' && $paidAt ? Carbon::createFromTimestamp($paidAt) : null,
            ],
        );
    }

    /**
     * Resolve the local plan from the invoice's first line price.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function resolvePlan(array $invoice): ?SubscriptionPlan
    {
        $priceId = data_get($invoice, 'lines.data.0.price.id')
            ?? data_get($invoice, 'lines.data.0.plan.id');

        return $priceId ? SubscriptionPlan::where('stripe_price_id', $priceId)->first() : null;
    }
}
