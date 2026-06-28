<?php

use App\Models\Ad;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

test('admins receive a paginated list of ads', function () {
    actingAsAdmin();

    Ad::factory()->count(3)->create();

    $this->getJson('/api/admin/ads')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('ads can be filtered by live status', function () {
    actingAsAdmin();

    Ad::factory()->count(2)->create();
    Ad::factory()->paused()->create();

    $this->getJson('/api/admin/ads?filter[status]=live')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('ads can be filtered by paused status', function () {
    actingAsAdmin();

    Ad::factory()->count(2)->create();
    Ad::factory()->paused()->create();

    $this->getJson('/api/admin/ads?filter[status]=paused')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', false);
});

test('admins can create an ad with an image', function () {
    Storage::fake('public');
    actingAsAdmin();

    $response = $this->post('/api/admin/ads', [
        'title' => 'Save 20% - Wellness Bundle',
        'description' => 'A year of mindful habits to elevate your wellbeing.',
        'image' => UploadedFile::fake()->image('ad.jpg', 1200, 628),
        'link' => 'https://yoursite.com/landing-page',
        'coupon_code' => '12312AFT',
        'expiration_date' => '2026-12-05',
    ], ['Accept' => 'application/json']);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Ad created successfully.')
        ->assertJsonPath('data.title', 'Save 20% - Wellness Bundle')
        ->assertJsonPath('data.coupon_code', '12312AFT')
        ->assertJsonPath('data.status', true);

    $ad = Ad::firstOrFail();
    expect($ad->image)->not->toBeNull();
    expect($ad->publish_date->toDateString())->toBe(now()->toDateString());
    Storage::disk('public')->assertExists($ad->image);
});

test('an ad can be created without an image', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/ads', [
        'title' => 'Buy One Get One - Skincare',
        'description' => '365 quotes to ignite your daily practice.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.image', null)
        ->assertJsonPath('data.status', true);
});

test('creating an ad requires a title and description', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/ads', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'description']);
});

test('the ad image must be a valid image file', function () {
    actingAsAdmin();

    $this->post('/api/admin/ads', [
        'title' => 'Flash Sale',
        'description' => 'Limited time offer.',
        'image' => UploadedFile::fake()->create('flyer.pdf', 100, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('admins can view a single ad', function () {
    actingAsAdmin();

    $ad = Ad::factory()->create();

    $this->getJson("/api/admin/ads/{$ad->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $ad->id);
});

test('admins can update an ad', function () {
    actingAsAdmin();

    $ad = Ad::factory()->create(['status' => true]);

    $this->putJson("/api/admin/ads/{$ad->id}", [
        'title' => 'Updated Title',
        'status' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Ad updated successfully.')
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.status', false);

    expect($ad->fresh()->status)->toBeFalse();
});

test('admins can toggle an ad between live and paused', function () {
    actingAsAdmin();

    $ad = Ad::factory()->create(['status' => true]);

    $this->postJson("/api/admin/ads/{$ad->id}/toggle-status")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Ad paused successfully.')
        ->assertJsonPath('data.status', false);

    expect($ad->fresh()->status)->toBeFalse();
});

test('admins can delete an ad and its image', function () {
    Storage::fake('public');
    actingAsAdmin();

    $path = UploadedFile::fake()->image('ad.jpg')->store('ads', 'public');
    $ad = Ad::factory()->create(['image' => $path]);

    Storage::disk('public')->assertExists($path);

    $this->deleteJson("/api/admin/ads/{$ad->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Ad deleted successfully.');

    expect(Ad::find($ad->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('guests cannot access ad management', function () {
    $this->getJson('/api/admin/ads')->assertUnauthorized();
});
