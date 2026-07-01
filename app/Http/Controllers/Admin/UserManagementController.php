<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BillingPeriod;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserListResource;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Subscription\UserPlanResolver;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

#[Group('Admin - User Managements')]
class UserManagementController extends Controller
{
    public function __construct(private UserPlanResolver $planResolver) {}

    #[Endpoint(title: 'List Users', description: 'Get a paginated list of users with their current subscription. Filter by signup date (filter[created_date]=2026-06-28), status (filter[status]=active|deactive|all) and subscription (filter[subscription]=monthly|yearly|lifetime|free).')]
    public function index(Request $request): JsonResponse
    {
        $users = QueryBuilder::for(
            User::query()
                ->with(['roles', 'subscriptions'])
                ->withoutRole(UserRole::Admin->value)
        )
            ->allowedFilters(
                AllowedFilter::scope('created_date'),
                AllowedFilter::scope('status'),
                AllowedFilter::callback('subscription', $this->filterBySubscription(...)),
            )
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        $this->planResolver->attachLabels($users->getCollection());

        return $this->paginatedResponse(UserListResource::collection($users));
    }

    #[Endpoint(title: 'Toggle User Status', description: 'Enable or disable a user account.')]
    public function toggle(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return $this->errorResponse(
                message: 'You cannot change your own account status.',
                status: HttpResponse::HTTP_FORBIDDEN,
            );
        }

        $user->update(['status' => ! $user->status]);

        $user->load('roles');
        $this->planResolver->attachLabels(new Collection([$user]));

        $message = $user->status ? 'User enabled successfully.' : 'User disabled successfully.';

        return $this->successResponse(
            data: new UserListResource($user),
            message: $message,
        );
    }

    /**
     * Constrain the user query to a subscription type.
     */
    private function filterBySubscription(Builder $query, string $value): void
    {
        match ($value) {
            'monthly' => $this->whereActiveOnPeriod($query, BillingPeriod::Monthly),
            'yearly' => $this->whereActiveOnPeriod($query, BillingPeriod::Yearly),
            'lifetime' => $query->whereHas('payments', fn (Builder $q) => $q->succeeded()->whereIn('subscription_plan_id', $this->lifetimePlanIds())),
            'free' => $query
                ->whereDoesntHave('subscriptions', fn (Builder $q) => $q->active())
                ->whereDoesntHave('payments', fn (Builder $q) => $q->succeeded()->whereIn('subscription_plan_id', $this->lifetimePlanIds())),
            default => null,
        };
    }

    /**
     * Limit to users with an active subscription on the given billing period.
     */
    private function whereActiveOnPeriod(Builder $query, BillingPeriod $period): void
    {
        $priceIds = SubscriptionPlan::query()
            ->where('billing_period', $period->value)
            ->pluck('stripe_price_id')
            ->filter()
            ->all();

        $query->whereHas('subscriptions', fn (Builder $q) => $q->active()->whereIn('stripe_price', $priceIds));
    }

    /**
     * Ids of one-time (lifetime) plans.
     *
     * @return array<int, int>
     */
    private function lifetimePlanIds(): array
    {
        return SubscriptionPlan::query()
            ->where('billing_period', BillingPeriod::OnePayment->value)
            ->pluck('id')
            ->all();
    }
}
