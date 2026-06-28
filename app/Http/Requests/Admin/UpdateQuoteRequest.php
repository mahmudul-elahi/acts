<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdateQuoteRequest extends FormRequest
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
            'quote' => ['sometimes', 'required', 'string', 'max:1000'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->filled('quote')) {
            $normalized['quote'] = Str::squish((string) $this->input('quote'));
        }

        if ($this->filled('author')) {
            $normalized['author'] = Str::squish((string) $this->input('author'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
