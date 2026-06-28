<?php

use App\Models\SubscriptionPlan;
use App\Services\Stripe\StripePlanService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;

uses(LazilyRefreshDatabase::class);

test('admins receive a paginated list of subscription plans', function () {
    actingAsAdmin();

    SubscriptionPlan::factory()->count(3)->create();

    $this->getJson('/api/admin/subscription-plans')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('plans can be filtered by billing period', function () {
    actingAsAdmin();

    SubscriptionPlan::factory()->monthly()->count(2)->create();
    SubscriptionPlan::factory()->lifetime()->create();

    $this->getJson('/api/admin/subscription-plans?filter[billing_period]=one_payment')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.billing_period', 'one_payment');
});

test('plans can be filtered by status', function () {
    actingAsAdmin();

    SubscriptionPlan::factory()->count(2)->create();
    SubscriptionPlan::factory()->inactive()->create();

    $this->getJson('/api/admin/subscription-plans?filter[status]=inactive')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', false);
});

test('a plan is created locally when Stripe is not configured', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/subscription-plans', [
        'badge_name' => 'Popular',
        'title' => 'Monthly',
        'sub_title' => 'For those ready to go deeper',
        'price' => 8.88,
        'billing_period' => 'month',
        'features' => ['Unlimited Digs', 'Ad-free experience'],
    ])
        ->assertCreated()
        ->assertJsonPath('message', 'Subscription plan created successfully.')
        ->assertJsonPath('data.title', 'Monthly')
        ->assertJsonPath('data.price', '8.88')
        ->assertJsonPath('data.currency', 'usd')
        ->assertJsonPath('data.billing_period', 'month')
        ->assertJsonPath('data.billing_period_label', 'Month')
        ->assertJsonPath('data.stripe_product_id', null)
        ->assertJsonPath('data.stripe_price_id', null);

    expect(SubscriptionPlan::firstOrFail()->features)->toBe(['Unlimited Digs', 'Ad-free experience']);
});

test('creating a recurring plan mirrors a product and price to Stripe', function () {
    actingAsAdmin();

    $this->mock(StripePlanService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('createProduct')->once()->with('Monthly', 'Go deeper')->andReturn('prod_123');
        $mock->shouldReceive('createPrice')->once()->with('prod_123', 888, 'usd', 'month')->andReturn('price_123');
    });

    $this->postJson('/api/admin/subscription-plans', [
        'title' => 'Monthly',
        'description' => 'Go deeper',
        'price' => 8.88,
        'billing_period' => 'month',
    ])
        ->assertCreated()
        ->assertJsonPath('data.stripe_product_id', 'prod_123')
        ->assertJsonPath('data.stripe_price_id', 'price_123');
});

test('creating a one payment plan mirrors a one-time Stripe price', function () {
    actingAsAdmin();

    $this->mock(StripePlanService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('createProduct')->once()->andReturn('prod_life');
        $mock->shouldReceive('createPrice')->once()->with('prod_life', 11111, 'usd', null)->andReturn('price_life');
    });

    $this->postJson('/api/admin/subscription-plans', [
        'title' => 'Lifetime',
        'price' => 111.11,
        'billing_period' => 'one_payment',
    ])
        ->assertCreated()
        ->assertJsonPath('data.stripe_price_id', 'price_life');
});

test('creating a plan requires a title, price and billing period', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/subscription-plans', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'price', 'billing_period']);
});

test('the billing period must be a valid value', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/subscription-plans', [
        'title' => 'Weird',
        'price' => 10,
        'billing_period' => 'weekly',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['billing_period']);
});

test('admins can view a single plan', function () {
    actingAsAdmin();

    $plan = SubscriptionPlan::factory()->create();

    $this->getJson("/api/admin/subscription-plans/{$plan->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $plan->id);
});

test('admins can update a plan', function () {
    actingAsAdmin();

    $plan = SubscriptionPlan::factory()->monthly()->create(['status' => true]);

    $this->putJson("/api/admin/subscription-plans/{$plan->id}", [
        'title' => 'Updated Monthly',
        'status' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Subscription plan updated successfully.')
        ->assertJsonPath('data.title', 'Updated Monthly')
        ->assertJsonPath('data.status', false);

    expect($plan->fresh()->status)->toBeFalse();
});

test('changing a plan price creates a new Stripe price and archives the old one', function () {
    actingAsAdmin();

    $plan = SubscriptionPlan::factory()->monthly()->create([
        'price' => 8.88,
        'stripe_product_id' => 'prod_abc',
        'stripe_price_id' => 'price_old',
    ]);

    $this->mock(StripePlanService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('updateProduct')->once();
        $mock->shouldReceive('createPrice')->once()->with('prod_abc', 999, 'usd', 'month')->andReturn('price_new');
        $mock->shouldReceive('archivePrice')->once()->with('price_old');
    });

    $this->putJson("/api/admin/subscription-plans/{$plan->id}", [
        'price' => 9.99,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.stripe_price_id', 'price_new');
});

test('admins can delete a plan and archive it on Stripe', function () {
    actingAsAdmin();

    $plan = SubscriptionPlan::factory()->create([
        'stripe_product_id' => 'prod_del',
        'stripe_price_id' => 'price_del',
    ]);

    $this->mock(StripePlanService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('archivePrice')->once()->with('price_del');
        $mock->shouldReceive('archiveProduct')->once()->with('prod_del');
    });

    $this->deleteJson("/api/admin/subscription-plans/{$plan->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Subscription plan deleted successfully.');

    expect(SubscriptionPlan::find($plan->id))->toBeNull();
});

test('guests cannot access subscription plan management', function () {
    $this->getJson('/api/admin/subscription-plans')->assertUnauthorized();
});
