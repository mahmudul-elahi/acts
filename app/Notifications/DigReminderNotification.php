<?php

namespace App\Notifications;

use App\Models\Dig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DigReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Dig $dig) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Use the semantic type for the database "type" column.
     */
    public function databaseType(object $notifiable): string
    {
        return 'meditation_reminder';
    }

    /**
     * Use the semantic type for the broadcast envelope.
     */
    public function broadcastType(): string
    {
        return 'meditation_reminder';
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
     * Build the shared notification payload.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'type' => 'meditation_reminder',
            'message' => "Complete your dig on {$this->dig->title}.",
            'dig_id' => $this->dig->getKey(),
        ];
    }
}
