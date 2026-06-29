<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Http\Resources\Admin\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPlanService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Admin - Subscription Plans')]
class SubscriptionPlanController extends Controller
{
    public function __construct(private SubscriptionPlanService $plans) {}

    #[Endpoint(title: 'List Subscription Plans', description: 'Get a paginated list of subscription plans. Filter by billing period (filter[billing_period]=month|year|one_payment) and status (filter[status]=active|inactive).')]
    public function index(Request $request): JsonResponse
    {
        $plans = QueryBuilder::for(SubscriptionPlan::query())
            ->allowedFilters(
                AllowedFilter::scope('billing_period'),
                AllowedFilter::scope('status'),
            )
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(SubscriptionPlanResource::collection($plans));
    }

    #[Endpoint(title: 'Create Subscription Plan', description: 'Create a plan. When Stripe is configured, a matching Stripe product and price are created automatically.')]
    public function store(StoreSubscriptionPlanRequest $request): JsonResponse
    {
        $plan = $this->plans->create($request->validated());

        return $this->createdResponse(
            data: new SubscriptionPlanResource($plan),
            message: 'Subscription plan created successfully.',
        );
    }

    #[Endpoint(title: 'Show Subscription Plan', description: 'Get a single subscription plan.')]
    public function show(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        return $this->successResponse(data: new SubscriptionPlanResource($subscriptionPlan));
    }

    #[Endpoint(title: 'Update Subscription Plan', description: 'Update a plan. Pricing changes create a new Stripe price and archive the previous one.')]
    public function update(UpdateSubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $plan = $this->plans->update($subscriptionPlan, $request->validated());

        return $this->successResponse(
            data: new SubscriptionPlanResource($plan),
            message: 'Subscription plan updated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Subscription Plan', description: 'Delete a plan and archive its Stripe product and price.')]
    public function destroy(SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $this->plans->delete($subscriptionPlan);

        return $this->successResponse(message: 'Subscription plan deleted successfully.');
    }
}
