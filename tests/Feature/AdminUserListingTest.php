<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(LazilyRefreshDatabase::class);

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
