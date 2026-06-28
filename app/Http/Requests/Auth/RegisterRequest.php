<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->filled('first_name')) {
            $normalized['first_name'] = Str::squish(
                (string) $this->input('first_name'),
            );
        }

        if ($this->filled('last_name')) {
            $normalized['last_name'] = Str::squish(
                (string) $this->input('last_name'),
            );
        }

        if ($this->filled('email')) {
            $normalized['email'] = Str::lower((string) $this->input('email'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }
}
