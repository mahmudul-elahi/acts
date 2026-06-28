<?php

use App\Models\Quote;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(LazilyRefreshDatabase::class);

test('admins receive a paginated list of quotes', function () {
    actingAsAdmin();

    Quote::factory()->count(3)->create();

    $this->getJson('/api/admin/quotes')
        ->assertSuccessful()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3);
});

test('quotes can be filtered by active status', function () {
    actingAsAdmin();

    Quote::factory()->count(2)->create();
    Quote::factory()->inactive()->create();

    $this->getJson('/api/admin/quotes?filter[status]=active')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('quotes can be filtered by inactive status', function () {
    actingAsAdmin();

    Quote::factory()->count(2)->create();
    Quote::factory()->inactive()->create();

    $this->getJson('/api/admin/quotes?filter[status]=inactive')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', false);
});

test('admins can create a single quote', function () {
    actingAsAdmin();

    $response = $this->postJson('/api/admin/quotes', [
        'quote' => 'The journey within is the most important journey you will ever take.',
        'author' => 'The Dig',
        'status' => true,
        'notes' => "Be The You That's More You",
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Quote created successfully.')
        ->assertJsonPath('data.author', 'The Dig')
        ->assertJsonPath('data.status', true);

    expect(Quote::where('author', 'The Dig')->exists())->toBeTrue();
});

test('creating a quote requires the quote text and author', function () {
    actingAsAdmin();

    $this->postJson('/api/admin/quotes', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['quote', 'author']);
});

test('admins can view a single quote', function () {
    actingAsAdmin();

    $quote = Quote::factory()->create();

    $this->getJson("/api/admin/quotes/{$quote->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.id', $quote->id);
});

test('admins can update a quote', function () {
    actingAsAdmin();

    $quote = Quote::factory()->create(['status' => true]);

    $this->putJson("/api/admin/quotes/{$quote->id}", [
        'quote' => 'In stillness, we find our truth.',
        'status' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Quote updated successfully.')
        ->assertJsonPath('data.quote', 'In stillness, we find our truth.')
        ->assertJsonPath('data.status', false);

    expect($quote->fresh()->status)->toBeFalse();
});

test('admins can delete a quote', function () {
    actingAsAdmin();

    $quote = Quote::factory()->create();

    $this->deleteJson("/api/admin/quotes/{$quote->id}")
        ->assertSuccessful()
        ->assertJsonPath('message', 'Quote deleted successfully.');

    expect(Quote::find($quote->id))->toBeNull();
});

test('admins can bulk upload quotes from a csv with headers', function () {
    actingAsAdmin();

    $csv = "quote,author,status,notes\n"
        ."In stillness we find our truth,The Dig,active,first note\n"
        ."Your emotions are messengers,Ancient Wisdom,inactive,second note\n";

    $file = UploadedFile::fake()->createWithContent('quotes.csv', $csv);

    $this->postJson('/api/admin/quotes/bulk-upload', ['file' => $file])
        ->assertSuccessful()
        ->assertJsonPath('data.imported', 2)
        ->assertJsonPath('data.skipped', 0);

    expect(Quote::count())->toBe(2);
    expect(Quote::where('author', 'Ancient Wisdom')->first()->status)->toBeFalse();
});

test('bulk upload without headers maps columns by position and skips empty rows', function () {
    actingAsAdmin();

    $csv = "Every dig brings you closer to your authentic self,The Dig,active\n"
        .",,\n"
        ."In stillness we find our truth,Ancient Wisdom,inactive\n";

    $file = UploadedFile::fake()->createWithContent('quotes.csv', $csv);

    $this->postJson('/api/admin/quotes/bulk-upload', ['file' => $file])
        ->assertSuccessful()
        ->assertJsonPath('data.imported', 2)
        ->assertJsonPath('data.skipped', 1);

    expect(Quote::count())->toBe(2);
});

test('bulk upload rejects unsupported file types', function () {
    actingAsAdmin();

    $file = UploadedFile::fake()->create('quotes.pdf', 10, 'application/pdf');

    $this->postJson('/api/admin/quotes/bulk-upload', ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('guests cannot access quote management', function () {
    $this->getJson('/api/admin/quotes')->assertUnauthorized();
});
