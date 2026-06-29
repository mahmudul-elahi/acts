<?php

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Cashier\Checkout;
use Mockery\MockInterface;
use Stripe\Checkout\Session;

uses(LazilyRefreshDatabase::class);

/**
 * A minimal "no access" status payload, matching SubscriptionService::status().
 *
 * @return array<string, mixed>
 */
function freeStatus(): array
{
    return [
        'is_premium' => false,
        'access_type' => 'none',
        'status' => 'none',
        'on_trial' => false,
        'trial_ends_at' => null,
        'on_grace_period' => false,
        'ends_at' => null,
        'plan' => null,
    ];
}

// ---------------------------------------------------------------------------
// Plans
// ---------------------------------------------------------------------------

test('it lists active plans cheapest first', function () {
    actingAsUser();

    SubscriptionPlan::factory()->monthly()->create(['price' => 8.88]);
    SubscriptionPlan::factory()->lifetime()->create(['price' => 111.11]);
    SubscriptionPlan::factory()->yearly()->create(['price' => 53.33]);
    SubscriptionPlan::factory()->inactive()->create(['price' => 1.00]);

    $this->getJson('/api/subscription-plans')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.price', '8.88')
        ->assertJsonPath('data.1.price', '53.33')
        ->assertJsonPath('data.2.price', '111.11')
        ->assertJsonPath('data.0.plan_name', 'Monthly')
        ->assertJsonPath('data.0.is_recurring', true)
        ->assertJsonPath('data.2.is_recurring', false);
});

test('guests cannot list plans', function () {
    $this->getJson('/api/subscription-plans')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Status
// ---------------------------------------------------------------------------

test('it reports no premium access for a free user', function () {
    actingAsUser();

    $this->getJson('/api/subscription')
        ->assertSuccessful()
        ->assertJsonPath('data.is_premium', false)
        ->assertJsonPath('data.access_type', 'none')
        ->assertJsonPath('data.status', 'none')
        ->assertJsonPath('data.on_trial', false)
        ->assertJsonPath('data.on_grace_period', false)
        ->assertJsonPath('data.plan', null);
});

test('it reports lifetime access with the purchased plan', function () {
    $user = actingAsUser(['lifetime_access' => true]);

    $plan = SubscriptionPlan::factory()->lifetime()->create();
    Payment::factory()->create([
        'user_id' => $user->id,
        'subscription_plan_id' => $plan->id,
        'type' => 'one_time',
    ]);

    $this->getJson('/api/subscription')
        ->assertSuccessful()
        ->assertJsonPath('data.is_premium', true)
        ->assertJsonPath('data.access_type', 'lifetime')
        ->assertJsonPath('data.status', 'lifetime')
        ->assertJsonPath('data.plan.id', $plan->id);
});

test('it reports an active recurring subscription', function (string $factoryState, string $accessType) {
    $user = actingAsUser();

    $plan = SubscriptionPlan::factory()->{$factoryState}()->create(['stripe_price_id' => 'price_active']);
    subscribeUser($user, 'price_active');

    $this->getJson('/api/subscription')
        ->assertSuccessful()
        ->assertJsonPath('data.is_premium', true)
        ->assertJsonPath('data.access_type', $accessType)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.plan.id', $plan->id);
})->with([
    'monthly' => ['monthly', 'monthly'],
    'yearly' => ['yearly', 'yearly'],
]);

test('guests cannot view status', function () {
    $this->getJson('/api/subscription')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Checkout
// ---------------------------------------------------------------------------

test('it creates a checkout session for a plan', function () {
    actingAsUser();

    $plan = SubscriptionPlan::factory()->monthly()->syncedToStripe()->create();

    $session = new Session('cs_test_123');
    $session->url = 'https://checkout.stripe.com/c/pay/cs_test_123';

    $this->mock(SubscriptionService::class, function (MockInterface $mock) use ($session) {
        $mock->shouldReceive('checkout')->once()->andReturn(new Checkout(null, $session));
    });

    $this->postJson('/api/subscription/checkout', ['plan_id' => $plan->id])
        ->assertSuccessful()
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.com/c/pay/cs_test_123')
        ->assertJsonPath('data.session_id', 'cs_test_123');
});

test('it rejects checkout when the user already has premium access', function () {
    actingAsUser(['lifetime_access' => true]);

    $plan = SubscriptionPlan::factory()->monthly()->syncedToStripe()->create();

    $this->postJson('/api/subscription/checkout', ['plan_id' => $plan->id])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You already have an active subscription.');
});

test('it rejects checkout when a subscription is still pending', function () {
    $user = actingAsUser();

    $plan = SubscriptionPlan::factory()->monthly()->syncedToStripe()->create();

    // A past_due subscription is not "valid" (no premium access) but has not
    // ended, so a new checkout would create a duplicate.
    $user->subscriptions()->forceCreate([
        'type' => 'default',
        'stripe_id' => 'sub_pending',
        'stripe_status' => 'past_due',
        'stripe_price' => 'price_pending',
        'quantity' => 1,
    ]);

    $this->postJson('/api/subscription/checkout', ['plan_id' => $plan->id])
        ->assertStatus(409)
        ->assertJsonPath('message', 'You already have an active subscription.');
});

test('it rejects checkout for a plan that is not on Stripe yet', function () {
    actingAsUser();

    $plan = SubscriptionPlan::factory()->monthly()->create(['stripe_price_id' => null]);

    $this->postJson('/api/subscription/checkout', ['plan_id' => $plan->id])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This plan is not available for purchase yet.');
});

test('checkout validates the plan id', function (array $payload) {
    actingAsUser();

    $this->postJson('/api/subscription/checkout', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_id']);
})->with([
    'missing' => [[]],
    'non-existent' => [['plan_id' => 999999]],
]);

test('checkout rejects an inactive plan', function () {
    actingAsUser();

    $plan = SubscriptionPlan::factory()->inactive()->syncedToStripe()->create();

    $this->postJson('/api/subscription/checkout', ['plan_id' => $plan->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan_id']);
});

test('guests cannot start checkout', function () {
    $this->postJson('/api/subscription/checkout', ['plan_id' => 1])->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Cancel
// ---------------------------------------------------------------------------

test('it cancels an active subscription', function () {
    actingAsUser();

    $this->mock(SubscriptionService::class, function (MockInterface $mock) {
        $mock->shouldReceive('cancel')->once()->andReturnTrue();
        $mock->shouldReceive('status')->once()->andReturn(freeStatus());
    });

    $this->postJson('/api/subscription/cancel')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Subscription cancelled. Access continues until the end of the billing period.');
});

test('it returns an error when there is no subscription to cancel', function () {
    actingAsUser();

    $this->postJson('/api/subscription/cancel')
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You do not have an active subscription to cancel.');
});

test('guests cannot cancel', function () {
    $this->postJson('/api/subscription/cancel')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Resume
// ---------------------------------------------------------------------------

test('it resumes a subscription within its grace period', function () {
    actingAsUser();

    $this->mock(SubscriptionService::class, function (MockInterface $mock) {
        $mock->shouldReceive('resume')->once()->andReturnTrue();
        $mock->shouldReceive('status')->once()->andReturn(freeStatus());
    });

    $this->postJson('/api/subscription/resume')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Subscription resumed.');
});

test('it returns an error when there is no resumable subscription', function () {
    actingAsUser();

    $this->postJson('/api/subscription/resume')
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'You do not have a resumable subscription.');
});

test('guests cannot resume', function () {
    $this->postJson('/api/subscription/resume')->assertUnauthorized();
});
