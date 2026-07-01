<?php

use App\Services\Dig\LevelService;

test('a fresh user with no xp is level 1 at the start of the bar', function () {
    expect((new LevelService)->forXp(0))->toBe([
        'level' => 1,
        'total_xp' => 0,
        'xp_into_level' => 0,
        'xp_per_level' => 160,
        'xp_to_next' => 160,
    ]);
});

test('partway through a level reports the progress into it', function () {
    expect((new LevelService)->forXp(120))->toBe([
        'level' => 1,
        'total_xp' => 120,
        'xp_into_level' => 120,
        'xp_per_level' => 160,
        'xp_to_next' => 40,
    ]);
});

test('hitting the threshold exactly rolls over to the next level', function () {
    expect((new LevelService)->forXp(160))->toMatchArray([
        'level' => 2,
        'xp_into_level' => 0,
        'xp_to_next' => 160,
    ]);
});

test('xp far past the first threshold keeps levelling linearly', function () {
    expect((new LevelService)->forXp(1420))->toMatchArray([
        'level' => 9,
        'xp_into_level' => 140,
        'xp_to_next' => 20,
    ]);
});
