<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

/**
 * Build a fake Socialite user and bind a mocked driver that returns it,
 * matching the `Socialite::driver($provider)->stateless()->userFromToken()` chain.
 *
 * @param  array<string, mixed>  $attributes
 */
function fakeSocialiteUser(string $provider, array $attributes): void
{
    $socialiteUser = (new SocialiteUser)->map($attributes);

    $driver = Mockery::mock();
    $driver->shouldReceive('stateless')->andReturnSelf();
    $driver->shouldReceive('userFromToken')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

test('apple login creates a new user, assigns the user role and returns a token', function () {
    Role::findOrCreate(UserRole::User->value, (string) config('auth.defaults.guard', 'web'));

    fakeSocialiteUser('apple', [
        'id' => 'apple-unique-id',
        'name' => 'Tim Apple',
        'email' => 'tim@example.com',
        'avatar' => null,
    ]);

    $response = $this->postJson('/api/auth/social', [
        'provider' => 'apple',
        'token' => 'fake-identity-token',
        'device_name' => 'iphone',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'Logged in successfully.')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'tim@example.com')
        ->assertJsonPath('data.user.first_name', 'Tim')
        ->assertJsonPath('data.user.last_name', 'Apple')
        ->assertJsonPath('data.user.role', UserRole::User->value);

    expect($response->json('data.token'))->not->toBeEmpty();

    $user = User::where('email', 'tim@example.com')->firstOrFail();
    expect($user->provider)->toBe('apple')
        ->and($user->provider_id)->toBe('apple-unique-id')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('apple login returns the existing user matched by provider id without duplicating', function () {
    Role::findOrCreate(UserRole::User->value, (string) config('auth.defaults.guard', 'web'));

    $existing = User::factory()->create([
        'email' => 'returning@example.com',
        'provider' => 'apple',
        'provider_id' => 'apple-returning-id',
    ]);

    // Apple omits name/email on subsequent logins; only the stable provider id is sent.
    fakeSocialiteUser('apple', [
        'id' => 'apple-returning-id',
        'name' => null,
        'email' => null,
        'avatar' => null,
    ]);

    $response = $this->postJson('/api/auth/social', [
        'provider' => 'apple',
        'token' => 'fake-identity-token',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.id', $existing->id);

    expect(User::where('provider_id', 'apple-returning-id')->count())->toBe(1);
});

test('apple login links the provider to an existing account matched by email', function () {
    Role::findOrCreate(UserRole::User->value, (string) config('auth.defaults.guard', 'web'));

    $existing = User::factory()->create([
        'email' => 'linkme@example.com',
        'provider' => null,
        'provider_id' => null,
    ]);

    fakeSocialiteUser('apple', [
        'id' => 'apple-link-id',
        'name' => 'Link Me',
        'email' => 'linkme@example.com',
        'avatar' => null,
    ]);

    $this->postJson('/api/auth/social', [
        'provider' => 'apple',
        'token' => 'fake-identity-token',
    ])->assertOk();

    $existing->refresh();
    expect($existing->provider)->toBe('apple')
        ->and($existing->provider_id)->toBe('apple-link-id');
});

test('social login rejects unsupported providers', function () {
    $this->postJson('/api/auth/social', [
        'provider' => 'facebook',
        'token' => 'fake-token',
    ])->assertStatus(422)->assertJsonValidationErrorFor('provider');
});
