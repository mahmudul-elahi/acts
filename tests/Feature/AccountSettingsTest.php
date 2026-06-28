<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

test('an authenticated user can view their profile', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.avatar', null);
});

test('a user can update their name and email', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/profile', [
        'first_name' => 'Md',
        'last_name' => 'Mansur',
        'email' => 'mansur@example.com',
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Profile updated successfully.')
        ->assertJsonPath('data.first_name', 'Md')
        ->assertJsonPath('data.email', 'mansur@example.com');

    expect($user->fresh()->last_name)->toBe('Mansur');
});

test('the email must be unique to another user', function () {
    $other = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/profile', ['email' => 'taken@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('a user can keep their own email when updating', function () {
    $user = User::factory()->create(['email' => 'me@example.com']);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/profile', [
        'first_name' => 'Same',
        'email' => 'me@example.com',
    ])->assertSuccessful();
});

test('a user can upload a profile photo', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->post('/api/auth/profile', [
        'avatar' => UploadedFile::fake()->image('me.jpg', 400, 400),
    ], ['Accept' => 'application/json'])
        ->assertSuccessful()
        ->assertJsonPath('data.avatar', fn ($avatar) => $avatar !== null);

    $path = $user->fresh()->avatar;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

test('uploading a new photo replaces the previous one', function () {
    Storage::fake('public');
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('avatars', 'public');
    $user = User::factory()->create(['avatar' => $oldPath]);
    Sanctum::actingAs($user);

    $this->post('/api/auth/profile', [
        'avatar' => UploadedFile::fake()->image('new.jpg'),
    ], ['Accept' => 'application/json'])->assertSuccessful();

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($user->fresh()->avatar);
});

test('a user can delete their profile photo', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('me.jpg')->store('avatars', 'public');
    $user = User::factory()->create(['avatar' => $path]);
    Sanctum::actingAs($user);

    $this->deleteJson('/api/auth/profile/avatar')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Profile photo removed successfully.')
        ->assertJsonPath('data.avatar', null);

    expect($user->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('the avatar must be a valid image', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->post('/api/auth/profile', [
        'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['avatar']);
});

test('a user can update their password with the correct current password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Password updated successfully.');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('the password update fails with an incorrect current password', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('the new password must be confirmed', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'mismatch-123',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('logging out revokes the current access token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    expect($user->tokens()->count())->toBe(1);

    $this->withToken($token)->postJson('/api/auth/logout')
        ->assertSuccessful()
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($user->fresh()->tokens()->count())->toBe(0);
});

test('guests cannot access account settings', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->putJson('/api/auth/profile')->assertUnauthorized();
    $this->deleteJson('/api/auth/profile/avatar')->assertUnauthorized();
    $this->putJson('/api/auth/password')->assertUnauthorized();
    $this->postJson('/api/auth/logout')->assertUnauthorized();
});
