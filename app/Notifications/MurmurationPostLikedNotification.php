<?php

namespace App\Notifications;

use App\Models\MurmurationPost;
use App\Models\User;
use App\Traits\BuildsMurmurationNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MurmurationPostLikedNotification extends Notification
{
    use BuildsMurmurationNotification, Queueable;

    public function __construct(public MurmurationPost $post, public User $liker) {}

    /**
     * Get the notification's delivery channels. Whether to send at all (the
     * recipient's reaction alert preference and once-per-like dedup) is decided
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
        return 'post_liked';
    }

    /**
     * Build the shared notification payload.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return $this->buildPayload(
            postId: $this->post->getKey(),
            actor: $this->liker,
            message: "{$this->actorName($this->liker)} liked your post.",
        );
    }
}
