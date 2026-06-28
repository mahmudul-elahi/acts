<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

test('users can register and receive an otp', function () {
    $response = $this->postJson('/api/auth/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'JOHN@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Registered successfully. Please verify your email with the OTP sent.');
});

test('users can log in and receive a sanctum token', function () {
    $guardName = (string) config('auth.defaults.guard', 'web');

    Role::findOrCreate(UserRole::User->value, $guardName);

    $user = User::factory()->create([
        'email' => 'jane@example.com',
    ]);

    $user->assignRole(UserRole::User->value);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'JANE@example.com',
        'password' => 'password',
        'device_name' => 'feature-test',
    ]);

    $response
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged in successfully.')
        ->assertJsonPath('data.user.email', 'jane@example.com')
        ->assertJsonPath('data.user.role', UserRole::User->value)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath(
            'data.token',
            fn (string $token): bool => str_contains($token, '|'),
        );

    expect(PersonalAccessToken::query()->count())->toBe(1);
});

test('users cannot log in with invalid credentials', function () {
    User::factory()->create([
        'email' => 'jane@example.com',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'The provided credentials are incorrect.')
        ->assertJsonPath(
            'errors.email.0',
            'The provided credentials are incorrect.',
        );

    expect(PersonalAccessToken::query()->count())->toBe(0);
});
