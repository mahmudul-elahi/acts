<?php

namespace App\Services;

use App\Models\MurmurationComment;
use App\Models\MurmurationPost;
use App\Models\User;
use App\Notifications\MurmurationCommentNotification;
use Illuminate\Notifications\Notification;

class MurmurationCommentService
{
    /**
     * Add a top-level comment to a post and alert the post author.
     */
    public function comment(MurmurationPost $post, User $user, string $body): MurmurationComment
    {
        $comment = $post->comments()->create([
            'user_id' => $user->getKey(),
            'body' => $body,
        ])->load('user');

        $this->notifyAuthorOfComment($post, $comment, $user);

        return $comment;
    }

    /**
     * Add the post author's reply to a comment and alert the commenter.
     *
     * Callers must enforce that the user is the post author and that the
     * comment is top-level and not already replied to.
     */
    public function reply(MurmurationComment $comment, User $user, string $body): MurmurationComment
    {
        $reply = MurmurationComment::create([
            'murmuration_post_id' => $comment->murmuration_post_id,
            'user_id' => $user->getKey(),
            'parent_id' => $comment->id,
            'body' => $body,
        ])->load('user');

        $this->notify($comment->user, $user, new MurmurationCommentNotification($reply, 'reply'));

        return $reply;
    }

    /**
     * Toggle the current user's like on a comment. Returns true when now liked.
     */
    public function toggleLike(MurmurationComment $comment, User $user): bool
    {
        return $comment->likers()->toggle($user->getKey())['attached'] !== [];
    }

    /**
     * Alert the post author only on a user's first top-level comment, deciding
     * from the comments table rather than scanning notifications.
     */
    private function notifyAuthorOfComment(MurmurationPost $post, MurmurationComment $comment, User $commenter): void
    {
        $isFirstComment = $post->comments()->topLevel()
            ->where('user_id', $commenter->getKey())
            ->count() === 1;

        if ($isFirstComment) {
            $this->notify($post->user, $commenter, new MurmurationCommentNotification($comment, 'comment'));
        }
    }

    /**
     * Send a notification to a recipient, skipping when they are the actor, no
     * longer exist, or have muted comment alerts.
     */
    private function notify(?User $recipient, User $actor, Notification $notification): void
    {
        if ($recipient && $recipient->getKey() !== $actor->getKey() && $recipient->wantsCommentAlerts()) {
            $recipient->notify($notification);
        }
    }
}
