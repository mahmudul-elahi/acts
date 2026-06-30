<?php

namespace App\Http\Resources\User;

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
        $answer = $this->relationLoaded('answers') ? $this->answers->first() : null;

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
            'is_completed' => $answer !== null,
            'answer' => $answer ? [
                'selected_option' => $answer->selected_option,
                'response' => $answer->response,
                'xp_awarded' => $answer->xp_awarded,
            ] : null,
        ];
    }
}
