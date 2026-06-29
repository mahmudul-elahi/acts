<?php

namespace App\Services;

use App\Models\MurmurationComment;
use App\Models\MurmurationPost;
use App\Models\User;

class MurmurationCommentService
{
    /**
     * Add a top-level comment to a post.
     */
    public function comment(MurmurationPost $post, User $user, string $body): MurmurationComment
    {
        return $post->comments()->create([
            'user_id' => $user->getKey(),
            'body' => $body,
        ])->load('user');
    }

    /**
     * Add the post author's reply to a comment.
     *
     * Callers must enforce that the user is the post author and that the
     * comment is top-level and not already replied to.
     */
    public function reply(MurmurationComment $comment, User $user, string $body): MurmurationComment
    {
        return MurmurationComment::create([
            'murmuration_post_id' => $comment->murmuration_post_id,
            'user_id' => $user->getKey(),
            'parent_id' => $comment->id,
            'body' => $body,
        ])->load('user');
    }

    /**
     * Toggle the current user's like on a comment. Returns true when now liked.
     */
    public function toggleLike(MurmurationComment $comment, User $user): bool
    {
        return $comment->likers()->toggle($user->getKey())['attached'] !== [];
    }
}
