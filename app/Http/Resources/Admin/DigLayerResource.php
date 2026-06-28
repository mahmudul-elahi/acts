<?php

namespace App\Http\Resources\Admin;

use App\Models\DigLayer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DigLayer
 */
class DigLayerResource extends JsonResource
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
            'position' => $this->position,
            'title' => $this->title,
            'question' => $this->question,
            'answer_type' => $this->answer_type->value,
            'xp' => $this->xp,
            'include_other' => $this->include_other,
            'options' => $this->options,
            'placeholder' => $this->placeholder,
        ];
    }
}
