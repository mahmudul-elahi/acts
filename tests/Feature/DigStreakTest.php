<?php

use App\Models\Dig;
use App\Models\DigLayer;
use App\Models\User;
use App\Services\Dig\DigProgressService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/**
 * Record a dig-layer answer for the user, dated on the given day. Each call
 * uses a fresh layer so the (user, layer) uniqueness constraint never trips.
 */
function answerOnDay(User $user, Dig $dig, string $date): void
{
    $layer = DigLayer::factory()->for($dig)->create();

    $user->digLayerAnswers()->create([
        'dig_id' => $dig->id,
        'dig_layer_id' => $layer->id,
        'xp_awarded' => 0,
    ])->forceFill(['created_at' => $date])->save();
}

function streakFor(User $user): int
{
    return app(DigProgressService::class)->currentStreak($user);
}

test('a user with no activity has a zero streak', function () {
    expect(streakFor(User::factory()->create()))->toBe(0);
});

test('answering today gives a streak of one', function () {
    $user = User::factory()->create();
    $dig = Dig::factory()->create();

    answerOnDay($user, $dig, now()->toDateTimeString());

    expect(streakFor($user))->toBe(1);
});

test('consecutive days build the streak', function () {
    $user = User::factory()->create();
    $dig = Dig::factory()->create();

    answerOnDay($user, $dig, now()->toDateTimeString());
    answerOnDay($user, $dig, now()->subDay()->toDateTimeString());
    answerOnDay($user, $dig, now()->subDays(2)->toDateTimeString());

    expect(streakFor($user))->toBe(3);
});

test('multiple answers on the same day count once', function () {
    $user = User::factory()->create();
    $dig = Dig::factory()->create();

    answerOnDay($user, $dig, now()->toDateTimeString());
    answerOnDay($user, $dig, now()->toDateTimeString());
    answerOnDay($user, $dig, now()->subDay()->toDateTimeString());

    expect(streakFor($user))->toBe(2);
});

test('a streak survives until a full day is missed (yesterday still counts)', function () {
    $user = User::factory()->create();
    $dig = Dig::factory()->create();

    answerOnDay($user, $dig, now()->subDay()->toDateTimeString());
    answerOnDay($user, $dig, now()->subDays(2)->toDateTimeString());

    expect(streakFor($user))->toBe(2);
});

test('a gap of two or more days resets the streak to zero', function () {
    $user = User::factory()->create();
    $dig = Dig::factory()->create();

    answerOnDay($user, $dig, now()->subDays(3)->toDateTimeString());
    answerOnDay($user, $dig, now()->subDays(4)->toDateTimeString());

    expect(streakFor($user))->toBe(0);
});

test('a gap in the middle stops the count at the break', function () {
    $user = User::factory()->create();
    $dig = Dig::factory()->create();

    answerOnDay($user, $dig, now()->toDateTimeString());
    answerOnDay($user, $dig, now()->subDay()->toDateTimeString());
    // missed 2 days ago
    answerOnDay($user, $dig, now()->subDays(3)->toDateTimeString());

    expect(streakFor($user))->toBe(2);
});
