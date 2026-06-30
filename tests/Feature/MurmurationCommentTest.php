<?php

use App\Models\MurmurationComment;
use App\Models\MurmurationPost;
use App\Models\User;
use App\Notifications\MurmurationCommentNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(LazilyRefreshDatabase::class);

test('a member can comment on a post', function () {
    $user = actingAsUser();
    $post = MurmurationPost::factory()->create();

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'Beautiful reflection.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Beautiful reflection.')
        ->assertJsonPath('data.author.id', $user->id);

    $this->assertDatabaseHas('murmuration_comments', [
        'murmuration_post_id' => $post->id,
        'user_id' => $user->id,
        'parent_id' => null,
    ]);
});

test('comments are listed top-level with the author reply nested', function () {
    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);

    $comment = MurmurationComment::factory()->create(['murmuration_post_id' => $post->id]);
    MurmurationComment::factory()->create([
        'murmuration_post_id' => $post->id,
        'parent_id' => $comment->id,
        'user_id' => $author->id,
        'body' => 'Thank you for sharing.',
    ]);

    $this->getJson("/api/murmuration/posts/{$post->id}/comments")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $comment->id)
        ->assertJsonPath('data.0.reply.body', 'Thank you for sharing.');
});

test('the post author can reply to a comment', function () {
    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $comment = MurmurationComment::factory()->create(['murmuration_post_id' => $post->id]);

    $this->postJson("/api/murmuration/comments/{$comment->id}/reply", ['body' => 'Thanks!'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Thanks!');

    $this->assertDatabaseHas('murmuration_comments', ['parent_id' => $comment->id, 'user_id' => $author->id]);
});

test('a member who is not the post author cannot reply', function () {
    $post = MurmurationPost::factory()->create();
    $comment = MurmurationComment::factory()->create(['murmuration_post_id' => $post->id]);

    actingAsUser();

    $this->postJson("/api/murmuration/comments/{$comment->id}/reply", ['body' => 'Hi'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only the post author can reply to comments.');
});

test('a comment can only be replied to once', function () {
    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $comment = MurmurationComment::factory()->create(['murmuration_post_id' => $post->id]);

    $this->postJson("/api/murmuration/comments/{$comment->id}/reply", ['body' => 'first'])->assertCreated();
    $this->postJson("/api/murmuration/comments/{$comment->id}/reply", ['body' => 'second'])
        ->assertStatus(409)
        ->assertJsonPath('message', 'This comment already has a reply.');
});

test('a reply cannot itself be replied to', function () {
    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $comment = MurmurationComment::factory()->create(['murmuration_post_id' => $post->id]);
    $reply = MurmurationComment::factory()->create([
        'murmuration_post_id' => $post->id,
        'parent_id' => $comment->id,
        'user_id' => $author->id,
    ]);

    $this->postJson("/api/murmuration/comments/{$reply->id}/reply", ['body' => 'nested'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You can only reply to a top-level comment.');
});

test('a member can like and unlike a comment', function () {
    actingAsUser();
    $comment = MurmurationComment::factory()->create();

    $this->postJson("/api/murmuration/comments/{$comment->id}/like")
        ->assertSuccessful()
        ->assertJsonPath('data.liked', true)
        ->assertJsonPath('data.likes_count', 1);

    $this->postJson("/api/murmuration/comments/{$comment->id}/like")
        ->assertSuccessful()
        ->assertJsonPath('data.liked', false)
        ->assertJsonPath('data.likes_count', 0);
});

test('a comment requires a body', function () {
    actingAsUser();
    $post = MurmurationPost::factory()->create();

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['body']);
});

test('guests cannot comment', function () {
    $post = MurmurationPost::factory()->create();

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'hi'])->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------------

test('commenting on a post notifies the author on database and broadcast', function () {
    Notification::fake();

    $author = User::factory()->create();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $commenter = actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'Lovely.'])->assertCreated();

    Notification::assertSentTo(
        $author,
        MurmurationCommentNotification::class,
        fn (MurmurationCommentNotification $notification, array $channels) => $notification->type === 'comment'
            && $notification->comment->user_id === $commenter->id
            && $channels === ['database', 'broadcast'],
    );
});

test('comment notifications respect the author comment alert setting', function () {
    Notification::fake();

    $author = User::factory()->create();
    $author->notificationSettings()->update(['comment_alerts' => false]);
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'Hidden.'])->assertCreated();

    Notification::assertNothingSentTo($author);
});

test('commenting on your own post does not notify yourself', function () {
    Notification::fake();

    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'Note to self.'])->assertCreated();

    Notification::assertNothingSentTo($author);
});

test('replying to a comment notifies the commenter', function () {
    Notification::fake();

    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $commenter = User::factory()->create();
    $comment = MurmurationComment::factory()->create([
        'murmuration_post_id' => $post->id,
        'user_id' => $commenter->id,
    ]);

    $this->postJson("/api/murmuration/comments/{$comment->id}/reply", ['body' => 'Thanks!'])->assertCreated();

    Notification::assertSentTo(
        $commenter,
        MurmurationCommentNotification::class,
        fn (MurmurationCommentNotification $notification) => $notification->type === 'reply',
    );
});

test('a comment notification stores the shared payload shape', function () {
    $author = User::factory()->create();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'Hi there'])->assertCreated();

    $notification = $author->notifications()->sole();
    $data = $notification->data;

    expect($notification->type)->toBe('comment')
        ->and($data)->toHaveKeys(['type', 'post_id', 'comment_id', 'parent_id', 'body', 'actor', 'message'])
        ->and($data['type'])->toBe('comment')
        ->and($data['comment_id'])->not->toBeNull()
        ->and($data['parent_id'])->toBeNull()
        ->and($data['body'])->toBe('Hi there');
});

test('a repeat comment from the same user notifies the author only once', function () {
    $author = User::factory()->create();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'one'])->assertCreated();
    $this->postJson("/api/murmuration/posts/{$post->id}/comments", ['body' => 'two'])->assertCreated();

    expect($author->notifications()->count())->toBe(1);
});
