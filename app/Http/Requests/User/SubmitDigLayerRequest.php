<?php

namespace App\Http\Requests\User;

use App\Enums\DigAnswerType;
use App\Models\DigLayer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitDigLayerRequest extends FormRequest
{
    /**
     * The sentinel value a client sends for the "Other" / custom choice on an
     * option layer that has `include_other` enabled.
     */
    public const OTHER = 'Other';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rules adapt to the route-bound layer: text layers take a free-text
     * `response`; option layers take a `selected_option` from the layer's
     * options (plus the "Other" sentinel + a `response` when `include_other`).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DigLayer $layer */
        $layer = $this->route('layer');

        if ($layer->answer_type === DigAnswerType::Text) {
            return [
                'response' => ['required', 'string', 'max:5000'],
                'selected_option' => ['prohibited'],
            ];
        }

        $allowed = $layer->options ?? [];

        if ($layer->include_other) {
            $allowed[] = self::OTHER;
        }

        return [
            'selected_option' => ['required', 'string', Rule::in($allowed)],
            'response' => [
                Rule::requiredIf($this->input('selected_option') === self::OTHER),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
