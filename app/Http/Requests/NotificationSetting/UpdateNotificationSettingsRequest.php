<?php

namespace App\Http\Requests\NotificationSetting;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
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
            'meditation_reminders' => ['sometimes', 'required', 'boolean'],
            'comment_alerts' => ['sometimes', 'required', 'boolean'],
            'subscription_alerts' => ['sometimes', 'required', 'boolean'],
            'post_react_alerts' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
