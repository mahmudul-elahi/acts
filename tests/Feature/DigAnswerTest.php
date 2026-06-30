<?php

use App\Models\Dig;
use App\Models\DigLayer;
use App\Models\DigLayerAnswer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// Today's dig
// ---------------------------------------------------------------------------

test("today's dig returns the active dig scheduled for today with progress", function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->withLayers(4)->create();

    $this->getJson('/api/digs/today')
        ->assertSuccessful()
        ->assertJsonPath('data.id', $dig->id)
        ->assertJsonPath('data.layers_total', 4)
        ->assertJsonPath('data.xp_total', 80)
        ->assertJsonPath('data.layers_completed', 0)
        ->assertJsonPath('data.is_completed', false)
        ->assertJsonCount(4, 'data.layers');
});

test("today's dig is null when none is scheduled, inactive, or for another day", function () {
    actingAsUser();
    Dig::factory()->scheduledFor('2000-01-01')->withLayers(4)->create();   // past
    Dig::factory()->withLayers(4)->create();                                // unscheduled
    Dig::factory()->inactive()->scheduledFor()->withLayers(4)->create();    // today but inactive

    $this->getJson('/api/digs/today')
        ->assertSuccessful()
        ->assertJsonPath('data', null);
});

test('guests cannot access digs', function () {
    $this->getJson('/api/digs/today')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Answering & XP
// ---------------------------------------------------------------------------

test('submitting an option answer stores it and awards xp once', function () {
    $user = actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for($dig)->create([
        'xp' => 20,
        'options' => ['Fear', 'Sadness'],
        'include_other' => false,
    ]);

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Sadness'])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Answer saved.')
        ->assertJsonPath('data.xp_earned', 20)
        ->assertJsonPath('data.layers_completed', 1)
        ->assertJsonPath('data.is_completed', true);

    $this->assertDatabaseHas('dig_layer_answers', [
        'user_id' => $user->id,
        'dig_layer_id' => $layer->id,
        'selected_option' => 'Sadness',
        'xp_awarded' => 20,
    ]);

    expect($user->digXp())->toBe(20);
});

test('re-answering a layer updates the response without awarding xp again', function () {
    $user = actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for($dig)->create([
        'xp' => 20,
        'options' => ['Fear', 'Sadness'],
        'include_other' => false,
    ]);

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Fear'])->assertSuccessful();
    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Sadness'])
        ->assertSuccessful()
        ->assertJsonPath('data.xp_earned', 20);

    expect($user->digXp())->toBe(20);
    expect(DigLayerAnswer::where('dig_layer_id', $layer->id)->count())->toBe(1);
    $this->assertDatabaseHas('dig_layer_answers', [
        'dig_layer_id' => $layer->id,
        'selected_option' => 'Sadness',
        'xp_awarded' => 20,
    ]);
});

// ---------------------------------------------------------------------------
// Validation
// ---------------------------------------------------------------------------

test('a text layer requires a response', function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for($dig)->text()->create();

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['response']);
});

test('an option layer rejects a value outside its options', function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for($dig)->create(['options' => ['Fear', 'Sadness'], 'include_other' => false]);

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Joy'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['selected_option']);
});

test('include_other accepts the Other sentinel but requires the custom response', function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for($dig)->create(['options' => ['Fear', 'Sadness'], 'include_other' => true]);

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Other'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['response']);

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Other', 'response' => 'My own answer'])
        ->assertSuccessful();

    $this->assertDatabaseHas('dig_layer_answers', [
        'dig_layer_id' => $layer->id,
        'selected_option' => 'Other',
        'response' => 'My own answer',
    ]);
});

test('a layer that does not belong to the dig is not found', function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for(Dig::factory()->scheduledFor()->create())->create();

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Fear'])
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Stats & show
// ---------------------------------------------------------------------------

test('stats returns the total earned xp and completion counts', function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $option = DigLayer::factory()->for($dig)->create(['xp' => 20, 'options' => ['Fear'], 'include_other' => false]);
    $journal = DigLayer::factory()->for($dig)->text()->create(['xp' => 20]);

    $this->postJson("/api/digs/{$dig->id}/layers/{$option->id}", ['selected_option' => 'Fear'])->assertSuccessful();
    $this->postJson("/api/digs/{$dig->id}/layers/{$journal->id}", ['response' => 'My reflection'])->assertSuccessful();

    $this->getJson('/api/digs/stats')
        ->assertSuccessful()
        ->assertJsonPath('data.total_xp', 40)
        ->assertJsonPath('data.level', 1)
        ->assertJsonPath('data.xp_into_level', 40)
        ->assertJsonPath('data.xp_per_level', 160)
        ->assertJsonPath('data.xp_to_next', 120)
        ->assertJsonPath('data.layers_completed', 2)
        ->assertJsonPath('data.digs_completed', 1);
});

test('stats reports the excavation level once enough xp crosses a threshold', function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for($dig)->create(['xp' => 160, 'options' => ['Fear'], 'include_other' => false]);

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Fear'])->assertSuccessful();

    $this->getJson('/api/digs/stats')
        ->assertSuccessful()
        ->assertJsonPath('data.total_xp', 160)
        ->assertJsonPath('data.level', 2)
        ->assertJsonPath('data.xp_into_level', 0)
        ->assertJsonPath('data.xp_to_next', 160);
});

test('show returns the dig with the user per-layer progress', function () {
    actingAsUser();
    $dig = Dig::factory()->scheduledFor()->create();
    $layer = DigLayer::factory()->for($dig)->create(['xp' => 20, 'options' => ['Fear', 'Sadness'], 'include_other' => false]);

    $this->postJson("/api/digs/{$dig->id}/layers/{$layer->id}", ['selected_option' => 'Fear'])->assertSuccessful();

    $this->getJson("/api/digs/{$dig->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $dig->id)
        ->assertJsonPath('data.layers.0.is_completed', true)
        ->assertJsonPath('data.layers.0.answer.selected_option', 'Fear')
        ->assertJsonPath('data.layers.0.answer.xp_awarded', 20);
});
