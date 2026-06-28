<?php

namespace App\Models;

use Database\Factories\DigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'status'])]
class Dig extends Model
{
    /** @use HasFactory<DigFactory> */
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
     * Get the ordered layers (questions) that make up this dig.
     */
    public function layers(): HasMany
    {
        return $this->hasMany(DigLayer::class)->orderBy('position');
    }

    /**
     * Limit results by status. Anything other than "active"/"inactive" applies no filter.
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
