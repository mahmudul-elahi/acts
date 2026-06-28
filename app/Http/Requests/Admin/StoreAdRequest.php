<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreAdRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'link' => ['nullable', 'url', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'publish_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:publish_date'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->filled('title') ? Str::squish((string) $this->input('title')) : $this->input('title'),
            'publish_date' => $this->input('publish_date', now()->toDateString()),
        ]);
    }
}
