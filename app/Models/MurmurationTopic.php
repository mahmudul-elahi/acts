<?php

namespace App\Models;

use Database\Factories\MurmurationTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'status'])]
class MurmurationTopic extends Model
{
    /** @use HasFactory<MurmurationTopicFactory> */
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
        ];
    }

    /**
     * The posts filed under this topic.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(MurmurationPost::class);
    }

    /**
     * Limit results to active topics.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', true);
    }

    /**
     * Match topics whose name contains the given term (typeahead).
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $query->where('name', 'like', '%'.$term.'%');
    }
}
