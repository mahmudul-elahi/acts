<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subscription\CheckoutSubscriptionRequest;
use App\Http\Resources\SubscriptionPlanResource;
use App\Http\Resources\SubscriptionStatusResource;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('User - Subscriptions')]
class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    #[Endpoint(title: 'List Subscription Plans', description: 'Get the active subscription plans for the subscribe screen.')]
    public function plans(): JsonResponse
    {
        return $this->successResponse(
            data: SubscriptionPlanResource::collection($this->subscriptions->activePlans()),
        );
    }

    #[Endpoint(title: 'Subscription Status', description: "Get the authenticated user's current subscription / premium access status.")]
    public function status(Request $request): JsonResponse
    {
        return $this->successResponse(
            data: new SubscriptionStatusResource($this->subscriptions->status($request->user())),
        );
    }

    #[Endpoint(title: 'Start Checkout', description: 'Create a Stripe Checkout session for a plan and return its URL to open in a WebView.')]
    public function checkout(CheckoutSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->subscription(SubscriptionService::SUBSCRIPTION_NAME);

        // Block when the user has access, or an in-progress subscription that
        // has not fully ended (e.g. incomplete or past_due), to avoid creating
        // a duplicate "default" subscription.
        if ($user->hasPremiumAccess() || ($subscription && ! $subscription->ended())) {
            return $this->errorResponse(
                message: 'You already have an active subscription.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $plan = SubscriptionPlan::findOrFail($request->integer('plan_id'));

        if (! $plan->stripe_price_id) {
            return $this->errorResponse(
                message: 'This plan is not available for purchase yet.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $checkout = $this->subscriptions->checkout($user, $plan, $request->boolean('with_trial'));

        return $this->successResponse(data: [
            'checkout_url' => $checkout->url,
            'session_id' => $checkout->id,
        ]);
    }

    #[Endpoint(title: 'Cancel Subscription', description: 'Cancel the active subscription at the end of the current billing period.')]
    public function cancel(Request $request): JsonResponse
    {
        if (! $this->subscriptions->cancel($request->user())) {
            return $this->errorResponse(message: 'You do not have an active subscription to cancel.');
        }

        return $this->successResponse(
            data: new SubscriptionStatusResource($this->subscriptions->status($request->user())),
            message: 'Subscription cancelled. Access continues until the end of the billing period.',
        );
    }

    #[Endpoint(title: 'Resume Subscription', description: 'Resume a cancelled subscription that is still within its grace period.')]
    public function resume(Request $request): JsonResponse
    {
        if (! $this->subscriptions->resume($request->user())) {
            return $this->errorResponse(message: 'You do not have a resumable subscription.');
        }

        return $this->successResponse(
            data: new SubscriptionStatusResource($this->subscriptions->status($request->user())),
            message: 'Subscription resumed.',
        );
    }
}
