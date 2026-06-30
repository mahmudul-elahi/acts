<?php

namespace App\Models;

use App\Enums\MurmurationPostType;
use Database\Factories\MurmurationPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'murmuration_topic_id', 'type', 'body', 'media_path', 'media_mime', 'status'])]
class MurmurationPost extends Model
{
    /** @use HasFactory<MurmurationPostFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MurmurationPostType::class,
            'status' => 'boolean',
        ];
    }

    /**
     * The member who created the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The topic the post is filed under.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(MurmurationTopic::class, 'murmuration_topic_id');
    }

    /**
     * All comments (including author replies) on the post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(MurmurationComment::class);
    }

    /**
     * The like records on the post, including the author-notified marker.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(MurmurationPostLike::class);
    }

    /**
     * Users who currently like the post (excludes soft-deleted unlikes).
     */
    public function likers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'murmuration_post_likes')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /**
     * Users who have saved (bookmarked) the post.
     */
    public function savers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'murmuration_post_saves')->withTimestamps();
    }

    /**
     * Resolve a displayable URL for the post media, if any.
     */
    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk('public')->url($this->media_path) : null;
    }

    /**
     * Limit results to active (published) posts.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', true);
    }

    /**
     * Limit results to posts under the topic with the given slug.
     */
    #[Scope]
    protected function topicSlug(Builder $query, string $slug): void
    {
        $query->whereHas('topic', fn (Builder $topic) => $topic->where('slug', $slug));
    }

    /**
     * Limit results by run state. Anything other than "active"/"inactive" applies no filter.
     */
    #[Scope]
    protected function status(Builder $query, string $value): void
    {
        match ($value) {
            'active' => $query->where('status', true),
            'inactive' => $query->where('status', false),
            default => $query,
        };
    }
}
