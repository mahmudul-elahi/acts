<?php

namespace App\Enums;

enum JournalType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';

    /**
     * Human readable label for the journal type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Image => 'Image',
            self::Audio => 'Audio',
        };
    }

    /**
     * Whether this journal type is restricted to premium users.
     */
    public function isPremium(): bool
    {
        return $this === self::Audio;
    }

    /**
     * Whether this journal type requires an uploaded media file.
     */
    public function requiresMedia(): bool
    {
        return $this !== self::Text;
    }
}
