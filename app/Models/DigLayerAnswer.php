<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'dig_id', 'dig_layer_id', 'selected_option', 'response', 'xp_awarded'])]
class DigLayerAnswer extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'xp_awarded' => 'integer',
        ];
    }

    /**
     * The user who answered.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The dig the answered layer belongs to.
     */
    public function dig(): BelongsTo
    {
        return $this->belongsTo(Dig::class);
    }

    /**
     * The layer that was answered.
     */
    public function digLayer(): BelongsTo
    {
        return $this->belongsTo(DigLayer::class);
    }
}
