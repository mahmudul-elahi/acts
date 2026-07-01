<?php

use App\Models\Dig;
use App\Models\User;
use App\Notifications\DigReminderNotification;
use App\Services\Dig\DigService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

/**
 * Create a dig through the service (the path admins use), which is what fires
 * the reminder.
 *
 * @param  array<string, mixed>  $overrides
 */
function publishDig(array $overrides = []): Dig
{
    return app(DigService::class)->create(array_merge([
        'title' => 'Emotional Body Excavation',
        'type' => 'emotional',
        'status' => true,
        'published_on' => now()->toDateString(),
        'layers' => [
            ['title' => 'Layer 1', 'question' => 'What comes up?', 'answer_type' => 'text', 'xp' => 20],
        ],
    ], $overrides));
}

test('creating an active dig notifies a member who wants reminders', function () {
    Notification::fake();
    $user = User::factory()->create();

    publishDig();

    Notification::assertSentTo(
        $user,
        DigReminderNotification::class,
        fn (DigReminderNotification $notification, array $channels) => $channels === ['database', 'broadcast'],
    );
});

test('creating an inactive (draft) dig notifies no one', function () {
    Notification::fake();
    User::factory()->create();

    publishDig(['status' => false]);

    Notification::assertNothingSent();
});

test('the reminder respects the meditation reminder setting', function () {
    Notification::fake();
    $user = User::factory()->create();
    $user->notificationSettings()->update(['meditation_reminders' => false]);

    publishDig();

    Notification::assertNothingSentTo($user);
});

test('the reminder is not sent to inactive members', function () {
    Notification::fake();
    $user = User::factory()->create(['status' => false]);

    publishDig();

    Notification::assertNothingSentTo($user);
});

test('the reminder surfaces in the notification feed naming the dig', function () {
    $user = User::factory()->create();

    publishDig(['title' => 'Emotional Body Excavation']);

    Sanctum::actingAs($user);

    $this->getJson('/api/notifications')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'meditation_reminder')
        ->assertJsonPath('data.0.data.message', 'Complete your dig on Emotional Body Excavation.');
});
