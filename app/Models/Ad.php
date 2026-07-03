<?php

namespace App\Models;

use Database\Factories\AdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'image', 'link', 'coupon_code', 'publish_date', 'expiration_date', 'status'])]
class Ad extends Model
{
    /** @use HasFactory<AdFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'publish_date' => 'date',
            'expiration_date' => 'date',
            'status' => 'boolean',
        ];
    }

    /**
     * Limit results by run state. Anything other than "live"/"paused" applies no filter.
     */
    #[Scope]
    protected function status(Builder $query, string $value): void
    {
        match ($value) {
            'live' => $query->where('status', true),
            'paused' => $query->where('status', false),
            default => $query,
        };
    }

    /**
     * Limit results to ads published on the given calendar date.
     */
    #[Scope]
    protected function publishDate(Builder $query, string $date): void
    {
        $query->whereDate('publish_date', $date);
    }

    /**
     * Limit results to ads that are currently live and within their date window.
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', true)
            ->where('publish_date', '<=', now()->toDateString())
            ->where(function (Builder $query): void {
                $query->whereNull('expiration_date')
                    ->orWhere('expiration_date', '>=', now()->toDateString());
            });
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class);
    }
}
