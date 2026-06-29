<?php

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Stripe\StripePaymentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;

uses(LazilyRefreshDatabase::class);

/**
 * Build a Stripe invoice webhook payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function invoiceWebhook(string $type, array $overrides = []): array
{
    return [
        'id' => 'evt_'.fake()->bothify('????????'),
        'type' => $type,
        'data' => ['object' => array_merge([
            'id' => 'in_'.fake()->bothify('????????'),
            'customer' => 'cus_test',
            'charge' => 'ch_test',
            'amount_paid' => 888,
            'amount_due' => 888,
            'currency' => 'usd',
            'status_transitions' => ['paid_at' => 1750000000],
            'lines' => ['data' => [['price' => ['id' => 'price_123']]]],
        ], $overrides)],
    ];
}

test('a successful invoice webhook records a payment', function () {
    $plan = SubscriptionPlan::factory()->monthly()->create(['stripe_price_id' => 'price_123']);

    $user = User::factory()->create();
    $user->stripe_id = 'cus_test';
    $user->save();

    $this->mock(StripePaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('cardForCharge')->once()->with('ch_test')->andReturn(['brand' => 'visa', 'last_four' => '4242']);
    });

    $this->postJson('/stripe/webhook', invoiceWebhook('invoice.payment_succeeded'))
        ->assertSuccessful();

    $payment = Payment::firstOrFail();

    expect($payment->user_id)->toBe($user->id)
        ->and($payment->subscription_plan_id)->toBe($plan->id)
        ->and($payment->amount)->toBe(888)
        ->and($payment->status)->toBe('succeeded')
        ->and($payment->card_brand)->toBe('visa')
        ->and($payment->card_last_four)->toBe('4242')
        ->and($payment->paid_at)->not->toBeNull();
});

test('a failed invoice webhook records a failed payment', function () {
    SubscriptionPlan::factory()->monthly()->create(['stripe_price_id' => 'price_123']);

    $this->mock(StripePaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('cardForCharge')->andReturn(['brand' => null, 'last_four' => null]);
    });

    $this->postJson('/stripe/webhook', invoiceWebhook('invoice.payment_failed'))
        ->assertSuccessful();

    $payment = Payment::firstOrFail();

    expect($payment->status)->toBe('failed')
        ->and($payment->paid_at)->toBeNull();
});

test('the same invoice is not recorded twice', function () {
    SubscriptionPlan::factory()->monthly()->create(['stripe_price_id' => 'price_123']);

    $this->mock(StripePaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('cardForCharge')->andReturn(['brand' => 'visa', 'last_four' => '4242']);
    });

    $payload = invoiceWebhook('invoice.payment_succeeded', ['id' => 'in_dupe']);

    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();
    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();

    expect(Payment::where('stripe_id', 'in_dupe')->count())->toBe(1);
});

test('unhandled webhook events are ignored', function () {
    $this->postJson('/stripe/webhook', invoiceWebhook('customer.updated'))
        ->assertSuccessful();

    expect(Payment::count())->toBe(0);
});

/**
 * Build a one-time Checkout session webhook payload (the "Lifetime" plan).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function checkoutSessionWebhook(array $overrides = []): array
{
    return [
        'id' => 'evt_'.fake()->bothify('????????'),
        'type' => 'checkout.session.completed',
        'data' => ['object' => array_merge([
            'id' => 'cs_'.fake()->bothify('????????'),
            'mode' => 'payment',
            'customer' => 'cus_test',
            'payment_intent' => 'pi_test',
            'amount_total' => 11111,
            'currency' => 'usd',
            'created' => 1750000000,
            'metadata' => [],
        ], $overrides)],
    ];
}

test('a completed lifetime checkout grants access and records the payment', function () {
    $plan = SubscriptionPlan::factory()->lifetime()->create();

    $user = User::factory()->create(['lifetime_access' => false]);
    $user->stripe_id = 'cus_test';
    $user->save();

    $this->mock(StripePaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('cardForPaymentIntent')->once()->with('pi_test')->andReturn(['brand' => 'visa', 'last_four' => '4242']);
    });

    $this->postJson('/stripe/webhook', checkoutSessionWebhook([
        'metadata' => ['subscription_plan_id' => $plan->id],
    ]))->assertSuccessful();

    expect($user->fresh()->lifetime_access)->toBeTrue();

    $payment = Payment::firstOrFail();

    expect($payment->user_id)->toBe($user->id)
        ->and($payment->subscription_plan_id)->toBe($plan->id)
        ->and($payment->type)->toBe('one_time')
        ->and($payment->amount)->toBe(11111)
        ->and($payment->status)->toBe('succeeded')
        ->and($payment->card_brand)->toBe('visa')
        ->and($payment->card_last_four)->toBe('4242')
        ->and($payment->stripe_id)->toBe('pi_test')
        ->and($payment->paid_at)->not->toBeNull();
});

test('a subscription-mode checkout session is ignored', function () {
    $user = User::factory()->create(['lifetime_access' => false]);
    $user->stripe_id = 'cus_test';
    $user->save();

    $this->postJson('/stripe/webhook', checkoutSessionWebhook(['mode' => 'subscription']))
        ->assertSuccessful();

    expect($user->fresh()->lifetime_access)->toBeFalse()
        ->and(Payment::count())->toBe(0);
});

test('a lifetime checkout for an unknown customer is ignored', function () {
    $this->postJson('/stripe/webhook', checkoutSessionWebhook(['customer' => 'cus_unknown']))
        ->assertSuccessful();

    expect(Payment::count())->toBe(0);
});

test('a completed lifetime checkout is recorded only once', function () {
    $user = User::factory()->create();
    $user->stripe_id = 'cus_test';
    $user->save();

    $this->mock(StripePaymentService::class, function (MockInterface $mock) {
        $mock->shouldReceive('cardForPaymentIntent')->andReturn(['brand' => null, 'last_four' => null]);
    });

    $payload = checkoutSessionWebhook(['payment_intent' => 'pi_dupe']);

    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();
    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();

    expect(Payment::where('stripe_id', 'pi_dupe')->count())->toBe(1);
});
