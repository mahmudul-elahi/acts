<?php

namespace App\Http\Resources\User;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionPlan
 */
class SubscriptionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'badge_name' => $this->badge_name,
            'title' => $this->title,
            'sub_title' => $this->sub_title,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_period' => $this->billing_period->value,
            'billing_period_label' => $this->billing_period->label(),
            'plan_name' => $this->billing_period->planName(),
            'is_recurring' => $this->billing_period->isRecurring(),
            'trial_days' => $this->trial_days,
            'has_trial' => $this->hasTrial(),
            'features' => $this->features ?? [],
        ];
    }
}
