<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A like on a Murmuration post. Soft-deleted on unlike so the `notified_at`
 * marker survives, keeping author alerts idempotent across re-likes.
 */
#[Fillable(['murmuration_post_id', 'user_id', 'notified_at'])]
class MurmurationPostLike extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    /**
     * The post that was liked.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(MurmurationPost::class, 'murmuration_post_id');
    }

    /**
     * The user who liked the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
