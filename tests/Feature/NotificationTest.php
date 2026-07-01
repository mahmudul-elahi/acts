<?php

use App\Models\MurmurationPost;
use App\Models\User;
use App\Services\Murmuration\MurmurationCommentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

/**
 * Store a database notification for the given user.
 *
 * @param  array<string, mixed>  $data
 */
function makeNotification(User $user, string $type = 'comment', array $data = [], bool $read = false, ?string $createdAt = null): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'data' => $data ?: ['message' => 'Something happened.'],
        'read_at' => $read ? now() : null,
        'created_at' => $createdAt ?? now(),
    ]);
}

test('guests cannot access notifications', function () {
    $this->getJson('/api/notifications')->assertUnauthorized();
});

test('a member sees only their own notifications, newest first', function () {
    $user = actingAsUser();

    makeNotification($user, 'comment', ['message' => 'Older'], read: true, createdAt: now()->subMinute()->toDateTimeString());
    makeNotification($user, 'post_liked', ['message' => 'Newer']);
    makeNotification(User::factory()->create(), 'comment', ['message' => 'Someone else']);

    $this->getJson('/api/notifications')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.data.message', 'Newer')
        ->assertJsonPath('data.0.is_read', false)
        ->assertJsonPath('data.1.is_read', true);
});

test('the unread count reflects only unread notifications', function () {
    $user = actingAsUser();

    makeNotification($user, 'comment', read: false);
    makeNotification($user, 'comment', read: false);
    makeNotification($user, 'comment', read: true);

    $this->getJson('/api/notifications/unread-count')
        ->assertSuccessful()
        ->assertJsonPath('data.unread_count', 2);
});

test('a member can mark a single notification as read', function () {
    $user = actingAsUser();
    makeNotification($user);
    $id = $user->notifications()->sole()->id;

    $this->postJson("/api/notifications/{$id}/read")
        ->assertSuccessful()
        ->assertJsonPath('data.is_read', true);

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('marking a notification that is not yours returns not found', function () {
    actingAsUser();
    $other = User::factory()->create();
    makeNotification($other);
    $id = $other->notifications()->sole()->id;

    $this->postJson("/api/notifications/{$id}/read")->assertNotFound();

    expect($other->unreadNotifications()->count())->toBe(1);
});

test('a member can mark all notifications as read', function () {
    $user = actingAsUser();
    makeNotification($user);
    makeNotification($user);

    $this->postJson('/api/notifications/read-all')
        ->assertSuccessful()
        ->assertJsonPath('message', 'All notifications marked as read.');

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('a murmuration comment surfaces in the post author notification feed', function () {
    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $commenter = User::factory()->create();

    app(MurmurationCommentService::class)->comment($post, $commenter, 'Love this reflection.');

    $this->getJson('/api/notifications')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'comment')
        ->assertJsonPath('data.0.data.post_id', $post->id);
});
