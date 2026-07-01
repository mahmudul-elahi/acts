<?php

namespace App\Notifications;

use App\Models\MurmurationComment;
use App\Traits\BuildsMurmurationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MurmurationCommentNotification extends Notification implements ShouldQueue
{
    use BuildsMurmurationNotification, Queueable;

    /**
     * @param  'comment'|'reply'  $type
     */
    public function __construct(public MurmurationComment $comment, public string $type) {}

    /**
     * Get the notification's delivery channels. Whether to send at all (the
     * recipient's comment alert preference and first-comment dedup) is decided
     * by the dispatching service.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation stored on the database channel.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    /**
     * The semantic notification type shared across every channel.
     */
    protected function notificationType(): string
    {
        return $this->type;
    }

    /**
     * Build the shared notification payload.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $name = $this->actorName($this->comment->user);

        return $this->buildPayload(
            postId: $this->comment->murmuration_post_id,
            actor: $this->comment->user,
            message: $this->type === 'reply'
                ? "{$name} replied to your comment."
                : "{$name} commented on your post.",
            commentId: $this->comment->getKey(),
            parentId: $this->comment->parent_id,
            body: Str::limit((string) $this->comment->body, 120),
        );
    }
}
