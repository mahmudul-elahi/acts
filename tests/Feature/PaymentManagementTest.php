<?php

use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('admins receive a paginated transaction log', function () {
    actingAsAdmin();

    Payment::factory()->count(3)->create();

    $this->getJson('/api/admin/payments')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.payment_method', 'Stripe');
});

test('payments can be filtered by status', function () {
    actingAsAdmin();

    Payment::factory()->count(2)->create();
    Payment::factory()->failed()->create();

    $this->getJson('/api/admin/payments?filter[status]=failed')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'failed');
});

test('the payments overview reports revenue and active subscription counts', function () {
    actingAsAdmin();

    $monthly = SubscriptionPlan::factory()->monthly()->create(['stripe_price_id' => 'price_m']);
    $yearly = SubscriptionPlan::factory()->yearly()->create(['stripe_price_id' => 'price_y']);
    $lifetime = SubscriptionPlan::factory()->lifetime()->create(['stripe_price_id' => 'price_l']);

    Payment::factory()->create(['subscription_plan_id' => $monthly->id, 'amount' => 1000, 'status' => 'succeeded']);
    Payment::factory()->create(['subscription_plan_id' => $yearly->id, 'amount' => 5000, 'status' => 'succeeded']);
    Payment::factory()->create(['subscription_plan_id' => $lifetime->id, 'amount' => 11111, 'type' => 'one_time', 'status' => 'succeeded']);

    $monthlyUser = User::factory()->create();
    $yearlyUserOne = User::factory()->create();
    $yearlyUserTwo = User::factory()->create();
    $monthlyUser->subscriptions()->forceCreate(['type' => 'default', 'stripe_id' => 'sub_m', 'stripe_status' => 'active', 'stripe_price' => 'price_m', 'quantity' => 1]);
    $yearlyUserOne->subscriptions()->forceCreate(['type' => 'default', 'stripe_id' => 'sub_y1', 'stripe_status' => 'active', 'stripe_price' => 'price_y', 'quantity' => 1]);
    $yearlyUserTwo->subscriptions()->forceCreate(['type' => 'default', 'stripe_id' => 'sub_y2', 'stripe_status' => 'active', 'stripe_price' => 'price_y', 'quantity' => 1]);

    $this->getJson('/api/admin/payments/overview')
        ->assertSuccessful()
        ->assertJsonPath('data.total_revenue', '171.11')
        ->assertJsonPath('data.total_payments', 3)
        ->assertJsonPath('data.monthly.active_subscriptions', 1)
        ->assertJsonPath('data.monthly.revenue', '10.00')
        ->assertJsonPath('data.yearly.active_subscriptions', 2)
        ->assertJsonPath('data.yearly.revenue', '50.00')
        ->assertJsonPath('data.lifetime.purchases', 1)
        ->assertJsonPath('data.lifetime.revenue', '111.11');
});

test('guests cannot access payments', function () {
    $this->getJson('/api/admin/payments')->assertUnauthorized();
    $this->getJson('/api/admin/payments/overview')->assertUnauthorized();
});
