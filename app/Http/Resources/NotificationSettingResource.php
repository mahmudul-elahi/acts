<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'meditation_reminders' => (bool) $this->meditation_reminders,
            'comment_alerts' => (bool) $this->comment_alerts,
            'subscription_alerts' => (bool) $this->subscription_alerts,
            'post_react_alerts' => (bool) $this->post_react_alerts,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
