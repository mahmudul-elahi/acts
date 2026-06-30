<?php

namespace App\Http\Resources\User;

use App\Models\Dig;
use App\Models\DigLayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Dig
 */
class DigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $layers = $this->relationLoaded('layers') ? $this->layers : new Collection;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'published_on' => $this->published_on?->toDateString(),
            'layers_total' => $layers->count(),
            'layers_completed' => $layers->filter($this->isAnswered(...))->count(),
            'xp_total' => (int) $layers->sum('xp'),
            'xp_earned' => (int) $layers->sum(fn (DigLayer $layer): int => $this->answerXp($layer)),
            'is_completed' => $layers->isNotEmpty() && $layers->every($this->isAnswered(...)),
            'layers' => DigLayerResource::collection($this->whenLoaded('layers')),
        ];
    }

    /**
     * Whether the viewer has answered the given (answer-loaded) layer.
     */
    private function isAnswered(DigLayer $layer): bool
    {
        return $layer->relationLoaded('answers') && $layer->answers->isNotEmpty();
    }

    /**
     * The XP the viewer earned for the given layer (0 when unanswered).
     */
    private function answerXp(DigLayer $layer): int
    {
        return $layer->relationLoaded('answers')
            ? (int) ($layer->answers->first()?->xp_awarded ?? 0)
            : 0;
    }
}
