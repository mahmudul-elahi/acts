<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateSubscriptionPlanRequest extends FormRequest
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
            'badge_name' => ['nullable', 'string', 'max:50'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_period' => ['sometimes', 'required', Rule::enum(BillingPeriod::class)],
            'trial_days' => ['nullable', 'integer', 'min:2', 'max:365'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('title')) {
            $this->merge(['title' => Str::squish((string) $this->input('title'))]);
        }

        if ($this->filled('currency')) {
            $this->merge(['currency' => Str::lower((string) $this->input('currency'))]);
        }
    }
}
