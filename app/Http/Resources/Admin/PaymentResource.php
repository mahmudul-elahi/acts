<?php

namespace App\Http\Resources\Admin;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
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
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user ? trim("{$this->user->first_name} {$this->user->last_name}") : null,
                'email' => $this->user?->email,
            ]),
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? [
                'id' => $this->plan->id,
                'title' => $this->plan->title,
                'billing_period' => $this->plan->billing_period->value,
                'label' => $this->plan->billing_period->planName(),
            ] : null),
            'payment_method' => 'Stripe',
            'card_brand' => $this->card_brand,
            'card_last_four' => $this->card_last_four,
            'amount' => number_format($this->amount / 100, 2, '.', ''),
            'currency' => strtoupper($this->currency),
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
