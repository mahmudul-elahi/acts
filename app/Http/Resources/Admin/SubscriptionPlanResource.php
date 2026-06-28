<?php

namespace App\Http\Resources\Admin;

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
            'features' => $this->features ?? [],
            'stripe_product_id' => $this->stripe_product_id,
            'stripe_price_id' => $this->stripe_price_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
