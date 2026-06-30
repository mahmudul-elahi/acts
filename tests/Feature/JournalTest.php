<?php

use App\Models\Journal;
use App\Models\JournalTag;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// Feed
// ---------------------------------------------------------------------------

test('the feed returns active journals with favorite metadata, tags and author', function () {
    $user = actingAsUser();

    $tag = JournalTag::factory()->create(['name' => 'Gratitude', 'slug' => 'gratitude']);
    $journal = Journal::factory()->create();
    $journal->tags()->attach($tag);
    $journal->favoriters()->attach($user->id);
    Journal::factory()->inactive()->create();

    $this->getJson('/api/journals')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $journal->id)
        ->assertJsonPath('data.0.favorites_count', 1)
        ->assertJsonPath('data.0.is_favorited', true)
        ->assertJsonPath('data.0.tags.0.slug', 'gratitude')
        ->assertJsonPath('data.0.author.id', $journal->user_id);
});

test('the feed can be scoped to the user own journals with mine=1', function () {
    $user = actingAsUser();

    $mine = Journal::factory()->create(['user_id' => $user->id]);
    Journal::factory()->create();

    $this->getJson('/api/journals?mine=1')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.is_mine', true);
});

test('the feed can be filtered by tag', function () {
    actingAsUser();

    $morning = JournalTag::factory()->create(['name' => 'Morning', 'slug' => 'morning']);
    $tagged = Journal::factory()->create();
    $tagged->tags()->attach($morning);
    Journal::factory()->create();

    $this->getJson('/api/journals?filter[tag]=morning')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $tagged->id);
});

test('the feed can be searched by title, body or tag name', function () {
    actingAsUser();

    $byTitle = Journal::factory()->create(['title' => 'Morning Reflections', 'body' => 'x']);
    $byBody = Journal::factory()->create(['title' => 'x', 'body' => 'grateful reflections today']);
    Journal::factory()->create(['title' => 'Unrelated', 'body' => 'nothing here']);

    $this->getJson('/api/journals?filter[search]=reflection')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $byBody->id)
        ->assertJsonPath('data.1.id', $byTitle->id);
});

test('the feed is cursor paginated for load-more without overlap', function () {
    actingAsUser();

    $journals = Journal::factory()->count(3)->create();
    [$oldest, $middle, $newest] = $journals->sortBy('id')->values()->all();

    $first = $this->getJson('/api/journals?per_page=2')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newest->id)
        ->assertJsonPath('data.1.id', $middle->id)
        ->assertJsonPath('meta.has_more', true);

    $this->getJson('/api/journals?per_page=2&cursor='.$first->json('meta.next_cursor'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $oldest->id)
        ->assertJsonPath('meta.has_more', false);
});

test('guests cannot view the feed', function () {
    $this->getJson('/api/journals')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Publishing
// ---------------------------------------------------------------------------

test('a member can publish a text journal with tags', function () {
    actingAsUser();

    $this->postJson('/api/journals', [
        'type' => 'text',
        'title' => 'Morning Reflections',
        'body' => 'Today I woke up feeling grateful.',
        'tags' => ['Gratitude', 'Morning'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'text')
        ->assertJsonPath('data.title', 'Morning Reflections')
        ->assertJsonPath('data.body', 'Today I woke up feeling grateful.')
        ->assertJsonCount(2, 'data.tags');

    $this->assertDatabaseHas('journals', ['title' => 'Morning Reflections']);
    $this->assertDatabaseHas('journal_tags', ['slug' => 'gratitude']);
    $this->assertDatabaseHas('journal_tags', ['slug' => 'morning']);
});

test('publishing reuses existing tags instead of duplicating them', function () {
    actingAsUser();

    JournalTag::factory()->create(['name' => 'Gratitude', 'slug' => 'gratitude']);

    $this->postJson('/api/journals', [
        'type' => 'text',
        'title' => 'A title',
        'body' => 'x',
        'tags' => ['gratitude', 'Gratitude '],
    ])->assertCreated()->assertJsonCount(1, 'data.tags');

    expect(JournalTag::where('slug', 'gratitude')->count())->toBe(1);
});

test('a member can publish an image journal', function () {
    Storage::fake('public');
    actingAsUser();

    $this->postJson('/api/journals', [
        'type' => 'image',
        'title' => 'Morning Reflections',
        'media' => UploadedFile::fake()->image('sunrise.jpg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'image');

    $journal = Journal::firstOrFail();

    expect($journal->media_path)->not->toBeNull();
    Storage::disk('public')->assertExists($journal->media_path);
});

test('audio journals are blocked for members without premium access', function () {
    Storage::fake('public');
    actingAsUser();

    $this->postJson('/api/journals', [
        'type' => 'audio',
        'title' => 'Voice note',
        'media' => UploadedFile::fake()->create('voice.mp3', 500, 'audio/mpeg'),
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Audio entries are a premium feature.');

    expect(Journal::count())->toBe(0);
});

test('premium members can publish audio journals', function () {
    Storage::fake('public');
    actingAsUser(['lifetime_access' => true]);

    $this->postJson('/api/journals', [
        'type' => 'audio',
        'title' => 'Voice note',
        'media' => UploadedFile::fake()->create('voice.mp3', 500, 'audio/mpeg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'audio');

    Storage::disk('public')->assertExists(Journal::firstOrFail()->media_path);
});

test('creating a journal validates input', function (array $payload, string $error) {
    actingAsUser();

    $this->postJson('/api/journals', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$error]);
})->with([
    'missing type' => [['title' => 'X', 'body' => 'y'], 'type'],
    'missing title' => [['type' => 'text', 'body' => 'y'], 'title'],
    'text without body' => [['type' => 'text', 'title' => 'X'], 'body'],
    'image without media' => [['type' => 'image', 'title' => 'X'], 'media'],
]);

// ---------------------------------------------------------------------------
// Show / update / delete
// ---------------------------------------------------------------------------

test('a deactivated journal is hidden from other members', function () {
    actingAsUser();
    $journal = Journal::factory()->inactive()->create();

    $this->getJson("/api/journals/{$journal->id}")->assertNotFound();
});

test('an author can view their own deactivated journal', function () {
    $user = actingAsUser();
    $journal = Journal::factory()->inactive()->create(['user_id' => $user->id]);

    $this->getJson("/api/journals/{$journal->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $journal->id);
});

test('an author can edit their own journal and re-sync its tags', function () {
    $user = actingAsUser();
    $journal = Journal::factory()->create(['user_id' => $user->id, 'title' => 'Old title']);
    $journal->tags()->attach(JournalTag::factory()->create(['name' => 'Old', 'slug' => 'old']));

    $this->putJson("/api/journals/{$journal->id}", [
        'type' => 'text',
        'title' => 'New title',
        'body' => 'Updated reflection.',
        'tags' => ['Evening'],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'New title')
        ->assertJsonPath('data.body', 'Updated reflection.')
        ->assertJsonCount(1, 'data.tags')
        ->assertJsonPath('data.tags.0.slug', 'evening');

    expect($journal->fresh()->tags()->pluck('slug')->all())->toBe(['evening']);
});

test('a member cannot edit another member journal', function () {
    actingAsUser();
    $journal = Journal::factory()->create(['title' => 'Theirs']);

    $this->putJson("/api/journals/{$journal->id}", ['type' => 'text', 'title' => 'Hacked', 'body' => 'x'])
        ->assertForbidden();

    expect($journal->fresh()->title)->toBe('Theirs');
});

test('updating an image journal replaces and removes the old media', function () {
    Storage::fake('public');
    $user = actingAsUser();

    $oldPath = UploadedFile::fake()->image('old.jpg')->store('journals', 'public');
    $journal = Journal::factory()->image()->create(['user_id' => $user->id, 'media_path' => $oldPath]);

    $this->post("/api/journals/{$journal->id}", [
        '_method' => 'PUT',
        'type' => 'image',
        'title' => 'Updated',
        'media' => UploadedFile::fake()->image('new.jpg'),
    ])->assertSuccessful();

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($journal->fresh()->media_path);
});

test('an author can delete their own journal', function () {
    $user = actingAsUser();
    $journal = Journal::factory()->create(['user_id' => $user->id]);

    $this->deleteJson("/api/journals/{$journal->id}")->assertSuccessful();

    $this->assertModelMissing($journal);
});

test('a member cannot delete another member journal', function () {
    actingAsUser();
    $journal = Journal::factory()->create();

    $this->deleteJson("/api/journals/{$journal->id}")->assertForbidden();

    $this->assertModelExists($journal);
});

test('deleting a journal removes its media', function () {
    Storage::fake('public');
    $user = actingAsUser();

    $path = UploadedFile::fake()->image('p.jpg')->store('journals', 'public');
    $journal = Journal::factory()->image()->create(['user_id' => $user->id, 'media_path' => $path]);

    $this->deleteJson("/api/journals/{$journal->id}")->assertSuccessful();

    Storage::disk('public')->assertMissing($path);
});

// ---------------------------------------------------------------------------
// Favorites
// ---------------------------------------------------------------------------

test('a member can favorite a journal and list favorites', function () {
    actingAsUser();
    $journal = Journal::factory()->create();

    $this->postJson("/api/journals/{$journal->id}/favorite")
        ->assertSuccessful()
        ->assertJsonPath('data.favorited', true)
        ->assertJsonPath('data.favorites_count', 1);

    $this->getJson('/api/journals/favorites')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $journal->id)
        ->assertJsonPath('data.0.is_favorited', true);

    $this->postJson("/api/journals/{$journal->id}/favorite")
        ->assertJsonPath('data.favorited', false);

    $this->getJson('/api/journals/favorites')->assertJsonCount(0, 'data');
});

// ---------------------------------------------------------------------------
// Tag typeahead
// ---------------------------------------------------------------------------

test('the tag typeahead returns matching tags', function () {
    actingAsUser();

    JournalTag::factory()->create(['name' => 'Gratitude', 'slug' => 'gratitude']);
    JournalTag::factory()->create(['name' => 'Growth', 'slug' => 'growth']);
    JournalTag::factory()->create(['name' => 'Evening', 'slug' => 'evening']);

    $this->getJson('/api/journals/tags?search=Gr')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});
