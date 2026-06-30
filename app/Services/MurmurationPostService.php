<?php

namespace App\Services;

use App\Enums\MurmurationPostType;
use App\Models\MurmurationPost;
use App\Models\MurmurationPostLike;
use App\Models\User;
use App\Notifications\MurmurationPostLikedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class MurmurationPostService
{
    private const MEDIA_DISK = 'public';

    private const MEDIA_DIR = 'murmuration';

    public function __construct(private MurmurationTopicService $topics) {}

    /**
     * Create a post, resolving its topic and storing any uploaded media.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data, ?UploadedFile $media = null): MurmurationPost
    {
        $topic = $this->topics->findOrCreate($data['topic']);

        $attributes = [
            'user_id' => $user->getKey(),
            'murmuration_topic_id' => $topic->id,
            'type' => MurmurationPostType::from($data['type']),
            'body' => $data['body'] ?? null,
            'status' => true,
        ];

        if ($media instanceof UploadedFile) {
            $attributes['media_path'] = $media->store(self::MEDIA_DIR, self::MEDIA_DISK);
            $attributes['media_mime'] = $media->getMimeType();
        }

        return MurmurationPost::create($attributes)->load(['user', 'topic']);
    }

    /**
     * Delete a post along with its stored media.
     */
    public function delete(MurmurationPost $post): void
    {
        if ($post->media_path) {
            Storage::disk(self::MEDIA_DISK)->delete($post->media_path);
        }

        $post->delete();
    }

    /**
     * Toggle the current user's like on a post. Returns true when now liked.
     *
     * Unlikes soft-delete the like so its author-notified marker survives, and
     * a new like alerts the post author at most once (never on a re-like).
     */
    public function toggleLike(MurmurationPost $post, User $user): bool
    {
        $like = $post->likes()->withTrashed()->firstWhere('user_id', $user->getKey());

        if ($like && ! $like->trashed()) {
            $like->delete();

            return false;
        }

        if ($like) {
            $like->restore();
        } else {
            $like = $post->likes()->create(['user_id' => $user->getKey()]);
        }

        $this->notifyAuthorOfLike($post, $like, $user);

        return true;
    }

    /**
     * Alert the post author the first time their post is liked by this user.
     * The like row's `notified_at` makes this idempotent across re-likes.
     */
    private function notifyAuthorOfLike(MurmurationPost $post, MurmurationPostLike $like, User $liker): void
    {
        $author = $post->user;

        if ($like->notified_at !== null
            || ! $author
            || $author->getKey() === $liker->getKey()
            || ! $author->wantsPostReactAlerts()) {
            return;
        }

        $author->notify(new MurmurationPostLikedNotification($post, $liker));

        $like->forceFill(['notified_at' => now()])->save();
    }

    /**
     * Toggle the current user's save on a post. Returns true when now saved.
     */
    public function toggleSave(MurmurationPost $post, User $user): bool
    {
        return $post->savers()->toggle($user->getKey())['attached'] !== [];
    }

    /**
     * Flip a post between active and inactive (admin moderation).
     */
    public function toggleStatus(MurmurationPost $post): MurmurationPost
    {
        $post->update(['status' => ! $post->status]);

        return $post;
    }
}
