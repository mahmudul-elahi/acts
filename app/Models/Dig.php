<?php

namespace App\Models;

use App\Enums\DigType;
use Database\Factories\DigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'type', 'status', 'published_on'])]
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
            'type' => DigType::class,
            'status' => 'boolean',
            'published_on' => 'date',
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
     * Limit results to the active dig(s) scheduled for the given date.
     */
    #[Scope]
    protected function publishedOn(Builder $query, string $date): void
    {
        $query->where('status', true)->whereDate('published_on', $date);
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
