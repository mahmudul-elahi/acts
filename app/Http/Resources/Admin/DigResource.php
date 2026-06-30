<?php

namespace App\Http\Resources\Admin;

use App\Models\Dig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type->value,
            'status' => $this->status,
            'published_on' => $this->published_on?->toDateString(),
            'total_layers' => (int) ($this->layers_count ?? 0),
            'points' => (int) ($this->layers_sum_xp ?? 0),
            'layers' => DigLayerResource::collection($this->whenLoaded('layers')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
