<?php

namespace App\Http\Requests\Journal;

use App\Enums\JournalType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalRequest extends FormRequest
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
     * Image entries accept JPEG/PNG/WEBP or MP4 video; audio entries accept
     * common audio formats. Both are capped at 12 MB. Text entries must not
     * send media.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::enum(JournalType::class)],
            'title' => ['required', 'string', 'max:120'],
            'body' => [Rule::requiredIf($type === JournalType::Text->value), 'nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'media' => match ($type) {
                JournalType::Image->value => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4', 'max:12288'],
                JournalType::Audio->value => ['required', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/mp4,audio/aac,audio/ogg,audio/webm', 'max:12288'],
                default => ['prohibited'],
            },
        ];
    }
}
