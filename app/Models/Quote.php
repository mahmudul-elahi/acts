<?php

namespace App\Models;

use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['quote', 'author', 'status', 'notes'])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'reaction_count' => 'integer',
        ];
    }

    /**
     * Get the user who created this quote.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Users who have favorited (bookmarked) the quote.
     */
    public function favoriters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quote_favorites')->withTimestamps();
    }

    /**
     * Limit results to active (published) quotes.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', true);
    }

    /**
     * Match quotes whose text, author, or notes contains the given term.
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where(function (Builder $query) use ($term): void {
            $query->where('quote', 'like', '%'.$term.'%')
                ->orWhere('author', 'like', '%'.$term.'%')
                ->orWhere('notes', 'like', '%'.$term.'%');
        });
    }

    /**
     * Limit results by account status. Anything other than "active"/"inactive" applies no filter.
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
