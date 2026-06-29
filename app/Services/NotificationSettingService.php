<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\User;

class NotificationSettingService
{
    /**
     * Get the user's notification settings, creating defaults if missing.
     */
    public function for(User $user): NotificationSetting
    {
        return $user->notificationSettings()->firstOrCreate();
    }

    /**
     * Update the user's notification preferences.
     *
     * @param  array<string, mixed>  $settings
     */
    public function update(User $user, array $settings): NotificationSetting
    {
        $notificationSettings = $this->for($user);
        $notificationSettings->update($settings);

        return $notificationSettings;
    }
}
