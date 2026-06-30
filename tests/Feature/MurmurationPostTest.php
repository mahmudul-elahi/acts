<?php

use App\Models\MurmurationComment;
use App\Models\MurmurationPost;
use App\Models\MurmurationTopic;
use App\Models\User;
use App\Notifications\MurmurationPostLikedNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// Feed
// ---------------------------------------------------------------------------

test('the feed returns active posts with engagement metadata', function () {
    $user = actingAsUser();

    $topic = MurmurationTopic::factory()->create();
    $post = MurmurationPost::factory()->create(['murmuration_topic_id' => $topic->id]);
    MurmurationPost::factory()->inactive()->create();

    $post->likers()->attach($user->id);
    MurmurationComment::factory()->create(['murmuration_post_id' => $post->id]);

    $this->getJson('/api/murmuration/posts')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $post->id)
        ->assertJsonPath('data.0.likes_count', 1)
        ->assertJsonPath('data.0.comments_count', 1)
        ->assertJsonPath('data.0.is_liked', true)
        ->assertJsonPath('data.0.is_saved', false)
        ->assertJsonPath('data.0.topic.id', $topic->id)
        ->assertJsonPath('data.0.author.id', $post->user_id);
});

test('the feed can be filtered by topic', function () {
    actingAsUser();

    $reflection = MurmurationTopic::factory()->create(['name' => 'Reflection', 'slug' => 'reflection']);
    $other = MurmurationTopic::factory()->create();

    MurmurationPost::factory()->create(['murmuration_topic_id' => $reflection->id]);
    MurmurationPost::factory()->create(['murmuration_topic_id' => $other->id]);

    $this->getJson('/api/murmuration/posts?filter[topic]=reflection')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.topic.slug', 'reflection');
});

test('the feed is cursor paginated for load-more without overlap', function () {
    actingAsUser();

    // Newest-first ordering falls back to id, so the highest id comes first.
    $posts = MurmurationPost::factory()->count(3)->create();
    [$oldest, $middle, $newest] = $posts->sortBy('id')->values()->all();

    $first = $this->getJson('/api/murmuration/posts?per_page=2')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newest->id)
        ->assertJsonPath('data.1.id', $middle->id)
        ->assertJsonPath('meta.has_more', true);

    expect($first->json('meta.next_cursor'))->not->toBeNull();

    $this->getJson('/api/murmuration/posts?per_page=2&cursor='.$first->json('meta.next_cursor'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $oldest->id)
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);
});

test('guests cannot view the feed', function () {
    $this->getJson('/api/murmuration/posts')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Publishing
// ---------------------------------------------------------------------------

test('a member can publish a text post', function () {
    actingAsUser();

    $this->postJson('/api/murmuration/posts', [
        'type' => 'text',
        'topic' => 'Who am I',
        'body' => 'My reflection for today.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'text')
        ->assertJsonPath('data.body', 'My reflection for today.')
        ->assertJsonPath('data.topic.name', 'Who am I');

    $this->assertDatabaseHas('murmuration_topics', ['slug' => 'who-am-i']);
    $this->assertDatabaseHas('murmuration_posts', ['type' => 'text', 'body' => 'My reflection for today.']);
});

test('publishing reuses an existing topic instead of duplicating it', function () {
    actingAsUser();

    MurmurationTopic::factory()->create(['name' => 'Reflection', 'slug' => 'reflection']);

    $this->postJson('/api/murmuration/posts', ['type' => 'text', 'topic' => 'reflection', 'body' => 'x'])
        ->assertCreated();

    expect(MurmurationTopic::where('slug', 'reflection')->count())->toBe(1);
});

test('a member can publish an image post', function () {
    Storage::fake('public');
    actingAsUser();

    $this->postJson('/api/murmuration/posts', [
        'type' => 'image',
        'topic' => 'The Four Bodies',
        'media' => UploadedFile::fake()->image('mandala.jpg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'image');

    $post = MurmurationPost::firstOrFail();

    expect($post->media_path)->not->toBeNull();
    Storage::disk('public')->assertExists($post->media_path);
});

test('audio posts are blocked for members without premium access', function () {
    Storage::fake('public');
    actingAsUser();

    $this->postJson('/api/murmuration/posts', [
        'type' => 'audio',
        'topic' => 'Reflection',
        'media' => UploadedFile::fake()->create('voice.mp3', 500, 'audio/mpeg'),
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Audio posts are a premium feature.');

    expect(MurmurationPost::count())->toBe(0);
});

test('premium members can publish audio posts', function () {
    Storage::fake('public');
    actingAsUser(['lifetime_access' => true]);

    $this->postJson('/api/murmuration/posts', [
        'type' => 'audio',
        'topic' => 'Reflection',
        'media' => UploadedFile::fake()->create('voice.mp3', 500, 'audio/mpeg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'audio');

    Storage::disk('public')->assertExists(MurmurationPost::firstOrFail()->media_path);
});

test('creating a post validates input', function (array $payload, string $error) {
    actingAsUser();

    $this->postJson('/api/murmuration/posts', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$error]);
})->with([
    'missing type' => [['topic' => 'X', 'body' => 'y'], 'type'],
    'missing topic' => [['type' => 'text', 'body' => 'y'], 'topic'],
    'text without body' => [['type' => 'text', 'topic' => 'X'], 'body'],
    'image without media' => [['type' => 'image', 'topic' => 'X'], 'media'],
]);

// ---------------------------------------------------------------------------
// Show / delete
// ---------------------------------------------------------------------------

test('a deactivated post is hidden from other members', function () {
    actingAsUser();
    $post = MurmurationPost::factory()->inactive()->create();

    $this->getJson("/api/murmuration/posts/{$post->id}")->assertNotFound();
});

test('an author can view their own deactivated post', function () {
    $user = actingAsUser();
    $post = MurmurationPost::factory()->inactive()->create(['user_id' => $user->id]);

    $this->getJson("/api/murmuration/posts/{$post->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $post->id);
});

test('an author can delete their own post', function () {
    $user = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $user->id]);

    $this->deleteJson("/api/murmuration/posts/{$post->id}")->assertSuccessful();

    $this->assertModelMissing($post);
});

test('a member cannot delete another member\'s post', function () {
    actingAsUser();
    $post = MurmurationPost::factory()->create();

    $this->deleteJson("/api/murmuration/posts/{$post->id}")->assertForbidden();

    $this->assertModelExists($post);
});

test('deleting a post removes its media', function () {
    Storage::fake('public');
    $user = actingAsUser();

    $path = UploadedFile::fake()->image('p.jpg')->store('murmuration', 'public');
    $post = MurmurationPost::factory()->image()->create(['user_id' => $user->id, 'media_path' => $path]);

    $this->deleteJson("/api/murmuration/posts/{$post->id}")->assertSuccessful();

    Storage::disk('public')->assertMissing($path);
});

// ---------------------------------------------------------------------------
// Likes / saves
// ---------------------------------------------------------------------------

test('a member can like and unlike a post', function () {
    actingAsUser();
    $post = MurmurationPost::factory()->create();

    $this->postJson("/api/murmuration/posts/{$post->id}/like")
        ->assertSuccessful()
        ->assertJsonPath('data.liked', true)
        ->assertJsonPath('data.likes_count', 1);

    $this->postJson("/api/murmuration/posts/{$post->id}/like")
        ->assertSuccessful()
        ->assertJsonPath('data.liked', false)
        ->assertJsonPath('data.likes_count', 0);
});

test('liking a post notifies the author on database and broadcast', function () {
    Notification::fake();

    $author = User::factory()->create();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $liker = actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/like")->assertSuccessful();

    Notification::assertSentTo(
        $author,
        MurmurationPostLikedNotification::class,
        fn (MurmurationPostLikedNotification $notification, array $channels) => $notification->liker->is($liker)
            && $channels === ['database', 'broadcast'],
    );
});

test('post like notifications respect the author reaction alert setting', function () {
    Notification::fake();

    $author = User::factory()->create();
    $author->notificationSettings()->update(['post_react_alerts' => false]);
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/like")->assertSuccessful();

    Notification::assertNothingSentTo($author);
});

test('liking your own post does not notify yourself', function () {
    Notification::fake();

    $author = actingAsUser();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);

    $this->postJson("/api/murmuration/posts/{$post->id}/like")->assertSuccessful();

    Notification::assertNothingSentTo($author);
});

test('a post like notification stores the shared payload shape', function () {
    $author = User::factory()->create();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    $liker = actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/like")->assertSuccessful();

    $notification = $author->notifications()->sole();
    $data = $notification->data;

    expect($notification->type)->toBe('post_liked')
        ->and($data)->toHaveKeys(['type', 'post_id', 'comment_id', 'parent_id', 'body', 'actor', 'message'])
        ->and($data['type'])->toBe('post_liked')
        ->and($data['comment_id'])->toBeNull()
        ->and($data['parent_id'])->toBeNull()
        ->and($data['body'])->toBeNull()
        ->and($data['actor'])->toMatchArray(['id' => $liker->id]);
});

test('liking, unliking, then liking again notifies the author only once', function () {
    $author = User::factory()->create();
    $post = MurmurationPost::factory()->create(['user_id' => $author->id]);
    actingAsUser();

    $this->postJson("/api/murmuration/posts/{$post->id}/like")->assertSuccessful(); // like
    $this->postJson("/api/murmuration/posts/{$post->id}/like")->assertSuccessful(); // unlike
    $this->postJson("/api/murmuration/posts/{$post->id}/like")->assertSuccessful(); // like again

    expect($author->notifications()->count())->toBe(1);
});

test('a member can save a post and list saved posts', function () {
    actingAsUser();
    $post = MurmurationPost::factory()->create();

    $this->postJson("/api/murmuration/posts/{$post->id}/save")
        ->assertSuccessful()
        ->assertJsonPath('data.saved', true);

    $this->getJson('/api/murmuration/posts/saved')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $post->id)
        ->assertJsonPath('data.0.is_saved', true);

    $this->postJson("/api/murmuration/posts/{$post->id}/save")
        ->assertJsonPath('data.saved', false);

    $this->getJson('/api/murmuration/posts/saved')->assertJsonCount(0, 'data');
});

// ---------------------------------------------------------------------------
// Topics typeahead
// ---------------------------------------------------------------------------

test('the topic typeahead returns matching topics', function () {
    actingAsUser();

    MurmurationTopic::factory()->create(['name' => 'Reflection', 'slug' => 'reflection']);
    MurmurationTopic::factory()->create(['name' => 'Relationships', 'slug' => 'relationships']);
    MurmurationTopic::factory()->create(['name' => 'Gratitude', 'slug' => 'gratitude']);

    $this->getJson('/api/murmuration/topics?search=Re')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
