<?php

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

test('every new user gets default notification settings', function () {
    $user = User::factory()->create();

    $settings = $user->notificationSettings;

    expect($settings)->not->toBeNull()
        ->meditation_reminders->toBeTrue()
        ->comment_alerts->toBeTrue()
        ->subscription_alerts->toBeTrue()
        ->post_react_alerts->toBeTrue();
});

test('authenticated users can fetch their notification settings', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/notification-settings')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.meditation_reminders', true)
        ->assertJsonPath('data.comment_alerts', true)
        ->assertJsonPath('data.subscription_alerts', true)
        ->assertJsonPath('data.post_react_alerts', true);
});

test('fetching notification settings requires authentication', function () {
    $this->getJson('/api/notification-settings')->assertUnauthorized();
});

test('authenticated users can update their notification settings', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->putJson('/api/notification-settings', [
        'meditation_reminders' => false,
        'post_react_alerts' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Notification settings updated successfully.')
        ->assertJsonPath('data.meditation_reminders', false)
        ->assertJsonPath('data.comment_alerts', true)
        ->assertJsonPath('data.subscription_alerts', true)
        ->assertJsonPath('data.post_react_alerts', false);

    expect($user->notificationSettings()->first())
        ->meditation_reminders->toBeFalse()
        ->comment_alerts->toBeTrue()
        ->post_react_alerts->toBeFalse();
});

test('notification settings update rejects non-boolean values', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/notification-settings', [
        'comment_alerts' => 'maybe',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('comment_alerts');
});

test('settings are lazily created for a user that has none', function () {
    // Simulate a user that predates the notification_settings table.
    $user = User::factory()->create();
    $user->notificationSettings()->delete();

    expect(NotificationSetting::query()->where('user_id', $user->id)->exists())->toBeFalse();

    Sanctum::actingAs($user);

    $this->getJson('/api/notification-settings')
        ->assertSuccessful()
        ->assertJsonPath('data.comment_alerts', true);

    expect(NotificationSetting::query()->where('user_id', $user->id)->exists())->toBeTrue();
});
