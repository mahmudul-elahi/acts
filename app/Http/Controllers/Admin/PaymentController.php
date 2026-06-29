<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingPeriod;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Laravel\Cashier\Subscription;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Admin - Payments')]
class PaymentController extends Controller
{
    #[Endpoint(title: 'List Payments', description: 'Get a paginated transaction log. Filter by status (filter[status]=succeeded|failed|refunded) and sort by paid_at, amount or created_at.')]
    public function index(Request $request): JsonResponse
    {
        $payments = QueryBuilder::for(Payment::query()->with(['user', 'plan']))
            ->allowedFilters(
                AllowedFilter::scope('status'),
            )
            ->allowedSorts('paid_at', 'amount', 'created_at')
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(PaymentResource::collection($payments));
    }

    #[Endpoint(title: 'Payments Overview', description: 'Get summary metrics: total revenue plus per-period revenue and active subscription counts.')]
    public function overview(): JsonResponse
    {
        $pricesByPeriod = SubscriptionPlan::query()
            ->whereNotNull('stripe_price_id')
            ->get(['stripe_price_id', 'billing_period'])
            ->groupBy(fn (SubscriptionPlan $plan): string => $plan->billing_period->value)
            ->map(fn ($group): array => $group->pluck('stripe_price_id')->all());

        $revenueByPeriod = Payment::query()
            ->where('payments.status', 'succeeded')
            ->join('subscription_plans', 'payments.subscription_plan_id', '=', 'subscription_plans.id')
            ->groupBy('subscription_plans.billing_period')
            ->selectRaw('subscription_plans.billing_period as period, SUM(payments.amount) as total')
            ->pluck('total', 'period');

        $overview = [
            'currency' => strtoupper((string) config('cashier.currency', 'usd')),
            'total_revenue' => $this->money((int) Payment::query()->succeeded()->sum('amount')),
            'total_payments' => Payment::query()->succeeded()->count(),
            'monthly' => [
                'active_subscriptions' => $this->activeSubscriptionCount($pricesByPeriod->get(BillingPeriod::Monthly->value, [])),
                'revenue' => $this->money((int) $revenueByPeriod->get(BillingPeriod::Monthly->value, 0)),
            ],
            'yearly' => [
                'active_subscriptions' => $this->activeSubscriptionCount($pricesByPeriod->get(BillingPeriod::Yearly->value, [])),
                'revenue' => $this->money((int) $revenueByPeriod->get(BillingPeriod::Yearly->value, 0)),
            ],
            'lifetime' => [
                'purchases' => Payment::query()->succeeded()->where('type', 'one_time')->count(),
                'revenue' => $this->money((int) $revenueByPeriod->get(BillingPeriod::OnePayment->value, 0)),
            ],
        ];

        return $this->successResponse(data: $overview);
    }

    /**
     * Count active subscriptions for the given Stripe price ids.
     *
     * @param  array<int, string>  $priceIds
     */
    private function activeSubscriptionCount(array $priceIds): int
    {
        if ($priceIds === []) {
            return 0;
        }

        return Subscription::query()->active()->whereIn('stripe_price', $priceIds)->count();
    }

    /**
     * Format an amount given in the smallest currency unit as a decimal string.
     */
    private function money(int $cents): string
    {
        return Number::format($cents / 100, precision: 2);
    }
}
