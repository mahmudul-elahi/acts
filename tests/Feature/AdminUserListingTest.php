<?php

use App\Enums\UserRole;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/**
 * Give a user an active Cashier subscription on the given Stripe price.
 */
function subscribeUser(User $user, string $stripePrice): void
{
    $user->subscriptions()->forceCreate([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->bothify('??????'),
        'stripe_status' => 'active',
        'stripe_price' => $stripePrice,
        'quantity' => 1,
    ]);
}

test('admins receive a paginated list excluding other admins', function () {
    actingAsAdmin();

    $members = User::factory()->count(3)->create();
    $members->each->assignRole(UserRole::User->value);

    $response = $this->getJson('/api/admin/users');

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('users can be filtered by the date they were created on', function () {
    actingAsAdmin();

    $today = User::factory()->create(['created_at' => now()]);
    User::factory()->create(['created_at' => now()->subDays(5)]);
    User::all()->each->assignRole(UserRole::User->value);

    $response = $this->getJson('/api/admin/users?filter[created_date]='.now()->toDateString());

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $today->id);
});

test('users can be filtered by active status', function () {
    actingAsAdmin();

    User::factory()->count(2)->create();
    User::factory()->disabled()->create();
    User::all()->each->assignRole(UserRole::User->value);

    $response = $this->getJson('/api/admin/users?filter[status]=active');

    $response
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('users can be filtered by deactive status', function () {
    actingAsAdmin();

    User::factory()->count(2)->create();
    $disabled = User::factory()->disabled()->create();
    User::all()->each->assignRole(UserRole::User->value);

    $response = $this->getJson('/api/admin/users?filter[status]=deactive');

    $response
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $disabled->id);
});

test('status filter defaults to all when set to all', function () {
    actingAsAdmin();

    User::factory()->count(2)->create();
    User::factory()->disabled()->create();
    User::all()->each->assignRole(UserRole::User->value);

    $response = $this->getJson('/api/admin/users?filter[status]=all');

    $response
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admins can disable an active user', function () {
    actingAsAdmin();

    $user = User::factory()->create();
    $user->assignRole(UserRole::User->value);

    $response = $this->postJson("/api/admin/users/{$user->id}/toggle-status");

    $response
        ->assertSuccessful()
        ->assertJsonPath('message', 'User disabled successfully.')
        ->assertJsonPath('data.status', false);

    expect($user->fresh()->status)->toBeFalse();
});

test('admins can enable a disabled user', function () {
    actingAsAdmin();

    $user = User::factory()->disabled()->create();
    $user->assignRole(UserRole::User->value);

    $response = $this->postJson("/api/admin/users/{$user->id}/toggle-status");

    $response
        ->assertSuccessful()
        ->assertJsonPath('message', 'User enabled successfully.')
        ->assertJsonPath('data.status', true);

    expect($user->fresh()->status)->toBeTrue();
});

test('admins cannot toggle their own account status', function () {
    $admin = actingAsAdmin();

    $response = $this->postJson("/api/admin/users/{$admin->id}/toggle-status");

    $response
        ->assertForbidden()
        ->assertJsonPath('message', 'You cannot change your own account status.');

    expect($admin->fresh()->status)->toBeTrue();
});

test('the user list reports a free subscription by default', function () {
    actingAsAdmin();

    $user = User::factory()->create();
    $user->assignRole(UserRole::User->value);

    $this->getJson('/api/admin/users')
        ->assertSuccessful()
        ->assertJsonPath('data.0.subscription', 'Free');
});

test('the user list reports the plan a subscribed user is on', function () {
    actingAsAdmin();

    SubscriptionPlan::factory()->yearly()->create(['stripe_price_id' => 'price_yearly']);

    $user = User::factory()->create();
    $user->assignRole(UserRole::User->value);
    subscribeUser($user, 'price_yearly');

    $this->getJson('/api/admin/users')
        ->assertSuccessful()
        ->assertJsonPath('data.0.subscription', 'Yearly');
});

test('users can be filtered by subscription type', function () {
    actingAsAdmin();

    SubscriptionPlan::factory()->monthly()->create(['stripe_price_id' => 'price_monthly']);
    SubscriptionPlan::factory()->yearly()->create(['stripe_price_id' => 'price_yearly']);

    $monthlyUser = User::factory()->create();
    $freeUser = User::factory()->create();
    $monthlyUser->assignRole(UserRole::User->value);
    $freeUser->assignRole(UserRole::User->value);
    subscribeUser($monthlyUser, 'price_monthly');

    $this->getJson('/api/admin/users?filter[subscription]=monthly')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $monthlyUser->id);

    $this->getJson('/api/admin/users?filter[subscription]=free')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $freeUser->id);
});
