<?php

namespace App\Http\Requests\Journal;

use App\Enums\JournalType;
use App\Models\Journal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJournalRequest extends FormRequest
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
     * Media is optional on update: an existing image/audio entry keeps its
     * current file unless a new one is supplied. Media only becomes required
     * when the entry is switching to a media type and has no file yet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->input('type');
        $journal = $this->route('journal');
        $hasMedia = $journal instanceof Journal && $journal->media_path !== null;

        return [
            'type' => ['required', Rule::enum(JournalType::class)],
            'title' => ['required', 'string', 'max:120'],
            'body' => [Rule::requiredIf($type === JournalType::Text->value), 'nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'media' => match ($type) {
                JournalType::Image->value => [Rule::requiredIf(! $hasMedia), 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4', 'max:12288'],
                JournalType::Audio->value => [Rule::requiredIf(! $hasMedia), 'nullable', 'file', 'mimetypes:audio/mpeg,audio/wav,audio/mp4,audio/aac,audio/ogg,audio/webm', 'max:12288'],
                default => ['prohibited'],
            },
        ];
    }
}
