<?php

namespace App\Services;

class LevelService
{
    /**
     * XP required to advance one excavation level. Flat across every level, so
     * thresholds fall on 0, 160, 320, 480, … and each level costs the same.
     */
    public const XP_PER_LEVEL = 160;

    /**
     * Break a total XP figure into the excavation-level progress shown on the
     * "Grow Yourself" card (current level + how far into it the user is).
     *
     * @return array{level: int, total_xp: int, xp_into_level: int, xp_per_level: int, xp_to_next: int}
     */
    public function forXp(int $xp): array
    {
        $perLevel = self::XP_PER_LEVEL;
        $intoLevel = $xp % $perLevel;

        return [
            'level' => intdiv($xp, $perLevel) + 1,
            'total_xp' => $xp,
            'xp_into_level' => $intoLevel,
            'xp_per_level' => $perLevel,
            'xp_to_next' => $perLevel - $intoLevel,
        ];
    }
}
