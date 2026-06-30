<?php

use App\Models\Dig;
use App\Models\DigLayer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('admins receive a paginated list of digs with layer count and points', function () {
    actingAsAdmin();

    Dig::factory()->count(3)->withLayers(4)->create();

    $this->getJson('/api/admin/digs')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('data.0.total_layers', 4)
        ->assertJsonPath('data.0.points', 80);
});

test('digs can be filtered by active status', function () {
    actingAsAdmin();

    Dig::factory()->count(2)->create();
    Dig::factory()->inactive()->create();

    $this->getJson('/api/admin/digs?filter[status]=active')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('digs can be filtered by inactive status', function () {
    actingAsAdmin();

    Dig::factory()->count(2)->create();
    Dig::factory()->inactive()->create();

    $this->getJson('/api/admin/digs?filter[status]=inactive')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', false);
});

test('admins can create a dig with layers', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/admin/digs', [
        'title' => 'Emotional Intelligence',
        'type' => 'emotional',
        'status' => true,
        'published_on' => '2026-07-01',
        'layers' => [
            [
                'title' => 'The Question',
                'question' => 'When you feel overwhelmed, what emotion is usually beneath the surface?',
                'answer_type' => 'option',
                'xp' => 20,
                'include_other' => true,
                'options' => ['Fear', 'Sadness', 'Anger'],
            ],
            [
                'title' => 'The Journal',
                'question' => 'Explore in your journal where or why this gets triggered.',
                'answer_type' => 'text',
                'xp' => 20,
                'placeholder' => 'Journal entry copies to their actual journal...',
            ],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Dig created successfully.')
        ->assertJsonPath('data.title', 'Emotional Intelligence')
        ->assertJsonPath('data.type', 'emotional')
        ->assertJsonPath('data.published_on', '2026-07-01')
        ->assertJsonPath('data.total_layers', 2)
        ->assertJsonPath('data.points', 40)
        ->assertJsonPath('data.layers.0.position', 1)
        ->assertJsonPath('data.layers.0.answer_type', 'option')
        ->assertJsonPath('data.layers.0.options', ['Fear', 'Sadness', 'Anger'])
        ->assertJsonPath('data.layers.1.position', 2)
        ->assertJsonPath('data.layers.1.answer_type', 'text')
        ->assertJsonPath('data.layers.1.placeholder', 'Journal entry copies to their actual journal...');

    expect(Dig::where('title', 'Emotional Intelligence')->exists())->toBeTrue();
    expect(DigLayer::count())->toBe(2);
});

test('creating a dig requires a title and at least one layer', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/digs', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'layers']);
});

test('option-type layers require options', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/digs', [
        'title' => 'Pattern Awareness',
        'layers' => [
            [
                'title' => 'The Question',
                'question' => 'What pattern do you notice?',
                'answer_type' => 'option',
            ],
        ],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['layers.0.options']);
});

test('admins can view a single dig with its layers', function () {
    actingAsAdmin();

    $dig = Dig::factory()->withLayers(4)->create();

    $this->getJson("/api/admin/digs/{$dig->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $dig->id)
        ->assertJsonPath('data.total_layers', 4)
        ->assertJsonPath('data.points', 80)
        ->assertJsonCount(4, 'data.layers');
});

test('admins can update a dig and replace its layers', function () {
    actingAsAdmin();

    $dig = Dig::factory()->withLayers(4)->create();

    $this->putJson("/api/admin/digs/{$dig->id}", [
        'title' => 'Updated Title',
        'layers' => [
            [
                'title' => 'Only Layer',
                'question' => 'A brand new question?',
                'answer_type' => 'text',
                'xp' => 10,
                'placeholder' => 'Write here...',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Dig updated successfully.')
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.total_layers', 1)
        ->assertJsonPath('data.points', 10)
        ->assertJsonCount(1, 'data.layers');

    expect(DigLayer::where('dig_id', $dig->id)->count())->toBe(1);
});

test('admins can toggle dig status without resending layers', function () {
    actingAsAdmin();

    $dig = Dig::factory()->withLayers(4)->create(['status' => true]);

    $this->patchJson("/api/admin/digs/{$dig->id}", ['status' => false])
        ->assertSuccessful()
        ->assertJsonPath('data.status', false)
        ->assertJsonPath('data.total_layers', 4);

    expect($dig->fresh()->status)->toBeFalse();
    expect(DigLayer::where('dig_id', $dig->id)->count())->toBe(4);
});

test('admins can delete a dig and its layers', function () {
    actingAsAdmin();

    $dig = Dig::factory()->withLayers(3)->create();

    $this->deleteJson("/api/admin/digs/{$dig->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Dig deleted successfully.');

    expect(Dig::find($dig->id))->toBeNull();
    expect(DigLayer::where('dig_id', $dig->id)->count())->toBe(0);
});

test('guests cannot access dig management', function () {
    $this->getJson('/api/admin/digs')->assertUnauthorized();
});
