<?php

namespace App\Models;

use Database\Factories\MurmurationCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['murmuration_post_id', 'user_id', 'parent_id', 'body'])]
class MurmurationComment extends Model
{
    /** @use HasFactory<MurmurationCommentFactory> */
    use HasFactory;

    /**
     * The post the comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(MurmurationPost::class, 'murmuration_post_id');
    }

    /**
     * The member who wrote the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The comment this one replies to, if any.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * The single author reply to this comment, if any.
     */
    public function reply(): HasOne
    {
        return $this->hasOne(self::class, 'parent_id');
    }

    /**
     * Users who have liked the comment.
     */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'murmuration_comment_likes')->withTimestamps();
    }

    /**
     * Limit results to top-level comments (excluding author replies).
     */
    #[Scope]
    protected function topLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }
}
