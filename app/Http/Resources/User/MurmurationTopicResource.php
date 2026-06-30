<?php

namespace App\Http\Resources\User;

use App\Models\MurmurationTopic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MurmurationTopic
 */
class MurmurationTopicResource extends JsonResource
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
            'posts_count' => $this->whenCounted('posts', fn (): int => (int) $this->posts_count),
        ];
    }
}
