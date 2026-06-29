<?php

namespace App\Http\Requests\Murmuration;

use App\Enums\MurmurationPostType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
     * Image posts accept JPEG/PNG/WEBP or MP4 video; audio posts accept common
     * audio formats. Both are capped at 12 MB. Text posts must not send media.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::enum(MurmurationPostType::class)],
            'topic' => ['required', 'string', 'max:50'],
            'body' => [Rule::requiredIf($type === MurmurationPostType::Text->value), 'nullable', 'string', 'max:5000'],
            'media' => match ($type) {
                MurmurationPostType::Image->value => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4', 'max:12288'],
                MurmurationPostType::Audio->value => ['required', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/mp4,audio/aac,audio/ogg,audio/webm', 'max:12288'],
                default => ['prohibited'],
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'topic' => $this->filled('topic') ? Str::squish((string) $this->input('topic')) : $this->input('topic'),
        ]);
    }
}
