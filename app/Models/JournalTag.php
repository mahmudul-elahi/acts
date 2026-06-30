<?php

namespace App\Models;

use Database\Factories\JournalTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug'])]
class JournalTag extends Model
{
    /** @use HasFactory<JournalTagFactory> */
    use HasFactory;

    /**
     * The entries filed under this tag.
     */
    public function journals(): BelongsToMany
    {
        return $this->belongsToMany(Journal::class);
    }

    /**
     * Match tags whose name contains the given term (typeahead).
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where('name', 'like', '%'.$term.'%');
    }
}
