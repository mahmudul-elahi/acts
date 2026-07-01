<?php

namespace App\Http\Resources\User;

use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quote
 */
class QuoteResource extends JsonResource
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
            'quote' => $this->quote,
            'author' => $this->author,
            'notes' => $this->notes,
            'favorites_count' => (int) ($this->favoriters_count ?? 0),
            'is_favorited' => (bool) ($this->favorited_by_user ?? false),
            'is_mine' => $request->user()?->getKey() === $this->user_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
