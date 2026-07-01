<?php

namespace App\Services\Journal;

use App\Enums\JournalType;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class JournalService
{
    private const MEDIA_DISK = 'public';

    private const MEDIA_DIR = 'journals';

    public function __construct(private JournalTagService $tags) {}

    /**
     * Create an entry, syncing its tags and storing any uploaded media.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data, ?UploadedFile $media = null): Journal
    {
        $attributes = [
            'user_id' => $user->getKey(),
            'type' => JournalType::from($data['type']),
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'status' => true,
        ];

        if ($media instanceof UploadedFile) {
            $attributes['media_path'] = $media->store(self::MEDIA_DIR, self::MEDIA_DISK);
            $attributes['media_mime'] = $media->getMimeType();
        }

        $journal = Journal::create($attributes);
        $journal->tags()->sync($this->tags->resolveIds($data['tags'] ?? []));

        return $journal->load(['user', 'tags']);
    }

    /**
     * Update an entry, replacing media only when a new file is supplied and
     * re-syncing tags only when they are present in the payload.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Journal $journal, array $data, ?UploadedFile $media = null): Journal
    {
        $attributes = [
            'type' => JournalType::from($data['type']),
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
        ];

        if ($media instanceof UploadedFile) {
            if ($journal->media_path) {
                Storage::disk(self::MEDIA_DISK)->delete($journal->media_path);
            }

            $attributes['media_path'] = $media->store(self::MEDIA_DIR, self::MEDIA_DISK);
            $attributes['media_mime'] = $media->getMimeType();
        }

        $journal->update($attributes);

        if (array_key_exists('tags', $data)) {
            $journal->tags()->sync($this->tags->resolveIds($data['tags'] ?? []));
        }

        return $journal->load(['user', 'tags']);
    }

    /**
     * Delete an entry along with its stored media.
     */
    public function delete(Journal $journal): void
    {
        if ($journal->media_path) {
            Storage::disk(self::MEDIA_DISK)->delete($journal->media_path);
        }

        $journal->delete();
    }

    /**
     * Toggle the current user's favorite on an entry. Returns true when now favorited.
     */
    public function toggleFavorite(Journal $journal, User $user): bool
    {
        return $journal->favoriters()->toggle($user->getKey())['attached'] !== [];
    }

    /**
     * Flip an entry between active and inactive (admin moderation).
     */
    public function toggleStatus(Journal $journal): Journal
    {
        $journal->update(['status' => ! $journal->status]);

        return $journal;
    }
}
