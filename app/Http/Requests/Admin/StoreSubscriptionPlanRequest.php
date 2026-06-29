<?php

namespace App\Http\Requests\Admin;

use App\Enums\BillingPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSubscriptionPlanRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'trial_days' => ['nullable', 'integer', 'min:2', 'max:365'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => $this->filled('title') ? Str::squish((string) $this->input('title')) : $this->input('title'),
            'currency' => Str::lower((string) $this->input('currency', config('cashier.currency', 'usd'))),
        ]);
    }
}
