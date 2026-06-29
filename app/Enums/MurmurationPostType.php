<?php

namespace App\Enums;

enum MurmurationPostType: string
{
    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';

    /**
     * Human readable label for the post type.
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
     * Whether this post type is restricted to premium users.
     */
    public function isPremium(): bool
    {
        return $this === self::Audio;
    }

    /**
     * Whether this post type requires an uploaded media file.
     */
    public function requiresMedia(): bool
    {
        return $this !== self::Text;
    }
}
