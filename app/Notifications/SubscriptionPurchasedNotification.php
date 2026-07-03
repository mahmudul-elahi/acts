<?php

namespace App\Notifications;

use App\Models\SubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SubscriptionPurchasedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SubscriptionPlan $plan) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function databaseType(object $notifiable): string
    {
        return 'subscription_purchased';
    }

    public function broadcastType(): string
    {
        return 'subscription_purchased';
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
            'type' => 'subscription_purchased',
            'message' => "You've successfully purchased the {$this->plan->title} plan.",
            'plan_name' => $this->plan->title,
        ];
    }
}
