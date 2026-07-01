<?php

use App\Models\Quote;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// Feed
// ---------------------------------------------------------------------------

test('the feed returns active quotes with favorite metadata', function () {
    $user = actingAsUser();

    $quote = Quote::factory()->create();
    $quote->favoriters()->attach($user->id);
    Quote::factory()->inactive()->create();

    $this->getJson('/api/quotes')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $quote->id)
        ->assertJsonPath('data.0.favorites_count', 1)
        ->assertJsonPath('data.0.is_favorited', true);
});

test('the feed can be scoped to the user own quotes with mine=1', function () {
    $user = actingAsUser();

    $mine = Quote::factory()->create(['user_id' => $user->id]);
    Quote::factory()->create();

    $this->getJson('/api/quotes?mine=1')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.is_mine', true);
});

test('the feed can be searched by quote, author or notes', function () {
    actingAsUser();

    $byQuote = Quote::factory()->create(['quote' => 'The cave you fear holds the treasure', 'author' => 'X', 'notes' => null]);
    $byAuthor = Quote::factory()->create(['quote' => 'x', 'author' => 'Treasure Hunter', 'notes' => null]);
    Quote::factory()->create(['quote' => 'unrelated', 'author' => 'nobody', 'notes' => null]);

    $this->getJson('/api/quotes?filter[search]=treasure')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $byAuthor->id)
        ->assertJsonPath('data.1.id', $byQuote->id);
});

test('the feed is cursor paginated for load-more without overlap', function () {
    actingAsUser();

    $quotes = Quote::factory()->count(3)->create();
    [$oldest, $middle, $newest] = $quotes->sortBy('id')->values()->all();

    $first = $this->getJson('/api/quotes?per_page=2')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newest->id)
        ->assertJsonPath('data.1.id', $middle->id)
        ->assertJsonPath('meta.has_more', true);

    $this->getJson('/api/quotes?per_page=2&cursor='.$first->json('meta.next_cursor'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $oldest->id)
        ->assertJsonPath('meta.has_more', false);
});

test('guests cannot view the feed', function () {
    $this->getJson('/api/quotes')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Publishing / validation
// ---------------------------------------------------------------------------

test('a member can publish a quote', function () {
    $user = actingAsUser();

    $this->postJson('/api/quotes', [
        'quote' => 'The cave you fear to enter holds the treasure you seek.',
        'author' => 'Kristen Crabtree',
        'notes' => 'Be The You That\'s More You',
    ])
        ->assertCreated()
        ->assertJsonPath('data.quote', 'The cave you fear to enter holds the treasure you seek.')
        ->assertJsonPath('data.author', 'Kristen Crabtree')
        ->assertJsonPath('data.notes', 'Be The You That\'s More You')
        ->assertJsonPath('data.is_mine', true);

    $this->assertDatabaseHas('quotes', ['author' => 'Kristen Crabtree', 'user_id' => $user->id]);
});

test('creating a quote validates input', function (array $payload, string $error) {
    actingAsUser();

    $this->postJson('/api/quotes', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors([$error]);
})->with([
    'missing quote' => [['author' => 'X'], 'quote'],
    'missing author' => [['quote' => 'x'], 'author'],
]);

// ---------------------------------------------------------------------------
// Show / update / delete
// ---------------------------------------------------------------------------

test('a deactivated quote is hidden from other members', function () {
    actingAsUser();
    $quote = Quote::factory()->inactive()->create();

    $this->getJson("/api/quotes/{$quote->id}")->assertNotFound();
});

test('an author can view their own deactivated quote', function () {
    $user = actingAsUser();
    $quote = Quote::factory()->inactive()->create(['user_id' => $user->id]);

    $this->getJson("/api/quotes/{$quote->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $quote->id);
});

test('an author can edit their own quote', function () {
    $user = actingAsUser();
    $quote = Quote::factory()->create(['user_id' => $user->id, 'author' => 'Old']);

    $this->putJson("/api/quotes/{$quote->id}", [
        'quote' => 'Updated wisdom.',
        'author' => 'New Author',
        'notes' => 'New notes',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.quote', 'Updated wisdom.')
        ->assertJsonPath('data.author', 'New Author');

    expect($quote->fresh()->author)->toBe('New Author');
});

test('a member cannot edit another member quote', function () {
    actingAsUser();
    $quote = Quote::factory()->create(['author' => 'Theirs']);

    $this->putJson("/api/quotes/{$quote->id}", ['quote' => 'x', 'author' => 'Hacked'])
        ->assertForbidden();

    expect($quote->fresh()->author)->toBe('Theirs');
});

test('an author can delete their own quote', function () {
    $user = actingAsUser();
    $quote = Quote::factory()->create(['user_id' => $user->id]);

    $this->deleteJson("/api/quotes/{$quote->id}")->assertSuccessful();

    $this->assertModelMissing($quote);
});

test('a member cannot delete another member quote', function () {
    actingAsUser();
    $quote = Quote::factory()->create();

    $this->deleteJson("/api/quotes/{$quote->id}")->assertForbidden();

    $this->assertModelExists($quote);
});

// ---------------------------------------------------------------------------
// Favorites
// ---------------------------------------------------------------------------

test('a member can favorite a quote and list favorites', function () {
    actingAsUser();
    $quote = Quote::factory()->create();

    $this->postJson("/api/quotes/{$quote->id}/favorite")
        ->assertSuccessful()
        ->assertJsonPath('data.favorited', true)
        ->assertJsonPath('data.favorites_count', 1);

    $this->getJson('/api/quotes/favorites')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $quote->id)
        ->assertJsonPath('data.0.is_favorited', true);

    $this->postJson("/api/quotes/{$quote->id}/favorite")
        ->assertJsonPath('data.favorited', false);

    $this->getJson('/api/quotes/favorites')->assertJsonCount(0, 'data');
});
