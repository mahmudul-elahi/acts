<?php

namespace App\Http\Resources;

use App\Models\MurmurationComment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MurmurationComment
 */
class MurmurationCommentResource extends JsonResource
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
            'body' => $this->body,
            'author' => $this->whenLoaded('user', fn (): ?array => $this->author($this->user)),
            'likes_count' => (int) ($this->likers_count ?? 0),
            'is_liked' => (bool) ($this->liked_by_user ?? false),
            'reply' => $this->whenLoaded('reply', fn (): ?array => $this->reply ? [
                'id' => $this->reply->id,
                'body' => $this->reply->body,
                'author' => $this->author($this->reply->user),
                'created_at' => $this->reply->created_at?->toISOString(),
            ] : null),
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
