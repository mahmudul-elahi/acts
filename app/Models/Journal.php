<?php

namespace App\Models;

use App\Enums\JournalType;
use Database\Factories\JournalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'type', 'title', 'body', 'media_path', 'media_mime', 'status'])]
class Journal extends Model
{
    /** @use HasFactory<JournalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JournalType::class,
            'status' => 'boolean',
        ];
    }

    /**
     * The member who wrote the journal entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The free-form tags attached to the entry.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(JournalTag::class);
    }

    /**
     * Users who have favorited (bookmarked) the entry.
     */
    public function favoriters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'journal_favorites')->withTimestamps();
    }

    /**
     * Resolve a displayable URL for the entry media, if any.
     */
    public function mediaUrl(): ?string
    {
        return $this->media_path ? Storage::disk('public')->url($this->media_path) : null;
    }

    /**
     * Limit results to active (published) entries.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', true);
    }

    /**
     * Limit results to entries tagged with the given slug.
     */
    #[Scope]
    protected function tagSlug(Builder $query, string $slug): void
    {
        $query->whereHas('tags', fn (Builder $tag) => $tag->where('slug', $slug));
    }

    /**
     * Match entries whose title, body, or any tag contains the given term.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', '%'.$term.'%')
                ->orWhere('body', 'like', '%'.$term.'%')
                ->orWhereHas('tags', fn (Builder $tag) => $tag->where('name', 'like', '%'.$term.'%'));
        });
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
