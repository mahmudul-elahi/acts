<?php

namespace App\Http\Requests\Admin;

use App\Enums\DigAnswerType;
use App\Enums\DigType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDigRequest extends FormRequest
{
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DigType::class)],
            'status' => ['sometimes', 'boolean'],
            'published_on' => ['nullable', 'date'],

            'layers' => ['required', 'array', 'min:1'],
            'layers.*.title' => ['required', 'string', 'max:255'],
            'layers.*.question' => ['required', 'string', 'max:2000'],
            'layers.*.answer_type' => ['required', Rule::enum(DigAnswerType::class)],
            'layers.*.xp' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'layers.*.include_other' => ['sometimes', 'boolean'],
            'layers.*.options' => ['nullable', 'required_if:layers.*.answer_type,'.DigAnswerType::Option->value, 'array', 'min:1'],
            'layers.*.options.*' => ['string', 'max:255'],
            'layers.*.placeholder' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('title')) {
            $this->merge(['title' => Str::squish((string) $this->input('title'))]);
        }
    }
}
