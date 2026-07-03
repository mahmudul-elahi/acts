<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Carbon $endsAt) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'subscription_cancelled';
    }

    public function broadcastType(): string
    {
        return 'subscription_cancelled';
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    private function payload(): array
    {
        return [
            'type' => 'subscription_cancelled',
            'message' => 'Your subscription has been cancelled. Access continues until '.$this->endsAt->format('j F, Y').'.',
            'ends_at' => $this->endsAt->toISOString(),
        ];
    }
}
