<?php

use App\Models\Journal;
use App\Models\JournalTag;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an admin sees all journals including deactivated ones', function () {
    actingAsAdmin();

    Journal::factory()->count(2)->create();
    Journal::factory()->inactive()->create();

    $this->getJson('/api/admin/journals')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('admin journals can be filtered by status', function () {
    actingAsAdmin();

    Journal::factory()->count(2)->create();
    Journal::factory()->inactive()->create();

    $this->getJson('/api/admin/journals?filter[status]=inactive')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', false);
});

test('admin journals can be filtered by tag', function () {
    actingAsAdmin();

    $tag = JournalTag::factory()->create(['name' => 'Gratitude', 'slug' => 'gratitude']);
    $tagged = Journal::factory()->create();
    $tagged->tags()->attach($tag);
    Journal::factory()->create();

    $this->getJson('/api/admin/journals?filter[tag]=gratitude')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $tagged->id);
});

test('an admin can view a single journal with metrics and author email', function () {
    actingAsAdmin();

    $journal = Journal::factory()->create();
    $journal->favoriters()->attach(User::factory()->create()->id);

    $this->getJson("/api/admin/journals/{$journal->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $journal->id)
        ->assertJsonPath('data.favorites_count', 1)
        ->assertJsonPath('data.author.email', $journal->user->email);
});

test('an admin can toggle a journal status', function () {
    actingAsAdmin();

    $journal = Journal::factory()->create(['status' => true]);

    $this->postJson("/api/admin/journals/{$journal->id}/toggle-status")
        ->assertSuccessful()
        ->assertJsonPath('data.status', false)
        ->assertJsonPath('message', 'Journal deactivated successfully.');

    expect($journal->fresh()->status)->toBeFalse();
});

test('an admin can delete a journal', function () {
    actingAsAdmin();

    $journal = Journal::factory()->create();

    $this->deleteJson("/api/admin/journals/{$journal->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Journal deleted successfully.');

    $this->assertModelMissing($journal);
});

test('members cannot access admin journal management', function () {
    actingAsUser();

    $this->getJson('/api/admin/journals')->assertForbidden();
});

test('guests cannot access admin journal management', function () {
    $this->getJson('/api/admin/journals')->assertUnauthorized();
});
