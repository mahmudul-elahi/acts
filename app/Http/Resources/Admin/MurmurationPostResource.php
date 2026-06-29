<?php

namespace App\Http\Resources\Admin;

use App\Models\MurmurationPost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MurmurationPost
 */
class MurmurationPostResource extends JsonResource
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
            'body' => $this->body,
            'media_url' => $this->mediaUrl(),
            'topic' => $this->whenLoaded('topic', fn (): ?array => $this->topic ? [
                'id' => $this->topic->id,
                'name' => $this->topic->name,
                'slug' => $this->topic->slug,
            ] : null),
            'author' => $this->whenLoaded('user', fn (): ?array => $this->author($this->user)),
            'likes_count' => (int) ($this->likers_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'status' => $this->status,
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
            'email' => $user->email,
        ];
    }
}
