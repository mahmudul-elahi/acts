<?php

namespace App\Http\Resources\User;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The underlying resource is the array returned by SubscriptionService::status().
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource['plan'] ?? null;

        return [
            'is_premium' => $this->resource['is_premium'],
            'access_type' => $this->resource['access_type'],
            'status' => $this->resource['status'],
            'on_trial' => $this->resource['on_trial'],
            'trial_ends_at' => $this->resource['trial_ends_at']?->toISOString(),
            'on_grace_period' => $this->resource['on_grace_period'],
            'ends_at' => $this->resource['ends_at']?->toISOString(),
            'plan' => $plan instanceof SubscriptionPlan
                ? new SubscriptionPlanResource($plan)
                : null,
        ];
    }
}
