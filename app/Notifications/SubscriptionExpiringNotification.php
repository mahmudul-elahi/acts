<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Carbon $expiresAt) {}

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
        return 'subscription_expiring';
    }

    /**
     * Use the semantic type for the broadcast envelope.
     */
    public function broadcastType(): string
    {
        return 'subscription_expiring';
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
            'type' => 'subscription_expiring',
            'message' => 'Your subscription will expire on '.$this->expiresAt->format('j F, Y').'.',
            'expires_at' => $this->expiresAt->toISOString(),
        ];
    }
}
