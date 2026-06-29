<?php

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Authenticate as a freshly created admin user and return it.
 */
function actingAsAdmin(): User
{
    $guardName = (string) config('auth.defaults.guard', 'web');

    Role::findOrCreate(UserRole::Admin->value, $guardName);
    Role::findOrCreate(UserRole::User->value, $guardName);

    $admin = User::factory()->create();
    $admin->assignRole(UserRole::Admin->value);

    Sanctum::actingAs($admin);

    return $admin;
}

/**
 * Authenticate as a freshly created (non-admin) user and return it.
 *
 * @param  array<string, mixed>  $attributes
 */
function actingAsUser(array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    Sanctum::actingAs($user);

    return $user;
}

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
