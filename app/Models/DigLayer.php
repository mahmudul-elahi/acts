<?php

namespace App\Models;

use App\Enums\DigAnswerType;
use Database\Factories\DigLayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position', 'title', 'question', 'answer_type', 'xp', 'include_other', 'options', 'placeholder'])]
class DigLayer extends Model
{
    /** @use HasFactory<DigLayerFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer_type' => DigAnswerType::class,
            'xp' => 'integer',
            'include_other' => 'boolean',
            'options' => 'array',
        ];
    }

    /**
     * Get the dig this layer belongs to.
     */
    public function dig(): BelongsTo
    {
        return $this->belongsTo(Dig::class);
    }
}
