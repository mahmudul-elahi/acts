<?php

use App\Models\MurmurationPost;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('an admin sees all posts including deactivated ones', function () {
    actingAsAdmin();

    MurmurationPost::factory()->count(2)->create();
    MurmurationPost::factory()->inactive()->create();

    $this->getJson('/api/admin/murmuration-posts')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('admin posts can be filtered by status', function () {
    actingAsAdmin();

    MurmurationPost::factory()->count(2)->create();
    MurmurationPost::factory()->inactive()->create();

    $this->getJson('/api/admin/murmuration-posts?filter[status]=inactive')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', false);
});

test('an admin can view a single post with metrics', function () {
    actingAsAdmin();

    $post = MurmurationPost::factory()->create();
    $post->likers()->attach(User::factory()->create()->id);

    $this->getJson("/api/admin/murmuration-posts/{$post->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.author.email', $post->user->email);
});

test('an admin can toggle a post status', function () {
    actingAsAdmin();

    $post = MurmurationPost::factory()->create(['status' => true]);

    $this->postJson("/api/admin/murmuration-posts/{$post->id}/toggle-status")
        ->assertSuccessful()
        ->assertJsonPath('data.status', false)
        ->assertJsonPath('message', 'Post deactivated successfully.');

    expect($post->fresh()->status)->toBeFalse();
});

test('an admin can delete a post', function () {
    actingAsAdmin();

    $post = MurmurationPost::factory()->create();

    $this->deleteJson("/api/admin/murmuration-posts/{$post->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Post deleted successfully.');

    $this->assertModelMissing($post);
});

test('members cannot access admin post management', function () {
    actingAsUser();

    $this->getJson('/api/admin/murmuration-posts')->assertForbidden();
});

test('guests cannot access admin post management', function () {
    $this->getJson('/api/admin/murmuration-posts')->assertUnauthorized();
});
