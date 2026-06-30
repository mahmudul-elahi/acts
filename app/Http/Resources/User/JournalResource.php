<?php

namespace App\Http\Resources\User;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Journal
 */
class JournalResource extends JsonResource
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
            'type' => $this->type->value,
            'title' => $this->title,
            'body' => $this->body,
            'media_url' => $this->mediaUrl(),
            'tags' => $this->whenLoaded('tags', fn (): array => $this->tags
                ->map(fn ($tag): array => ['id' => $tag->id, 'name' => $tag->name, 'slug' => $tag->slug])
                ->all()),
            'author' => $this->whenLoaded('user', fn (): ?array => $this->author($this->user)),
            'favorites_count' => (int) ($this->favoriters_count ?? 0),
            'is_favorited' => (bool) ($this->favorited_by_user ?? false),
            'is_mine' => $request->user()?->getKey() === $this->user_id,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Build the compact author payload.
     *
     * @return array<string, mixed>|null
     */
    private function author(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => trim("{$user->first_name} {$user->last_name}"),
            'avatar' => $user->avatarUrl(),
        ];
    }
}
