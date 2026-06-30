<?php

namespace App\Http\Resources\User;

use App\Models\JournalTag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalTag
 */
class JournalTagResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'journals_count' => $this->whenCounted('journals', fn (): int => (int) $this->journals_count),
        ];
    }
}
