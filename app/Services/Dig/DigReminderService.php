<?php

namespace App\Services\Dig;

use App\Models\Dig;
use App\Models\User;
use App\Notifications\DigReminderNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class DigReminderService
{
    /**
     * Notify every active member who wants meditation reminders that a new dig
     * is available to complete. Returns the number of members notified.
     */
    public function sendForDig(Dig $dig): int
    {
        $recipients = User::query()
            ->where('status', true)
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('notificationSettings')
                ->orWhereHas('notificationSettings', fn (Builder $settings) => $settings->where('meditation_reminders', true)))
            ->get();

        Notification::send($recipients, new DigReminderNotification($dig));

        return $recipients->count();
    }
}
