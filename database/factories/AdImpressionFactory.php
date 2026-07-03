<?php

namespace Database\Factories;

use App\Models\Ad;
use App\Models\AdImpression;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdImpression>
 */
class AdImpressionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ad_id' => Ad::factory(),
            'user_id' => User::factory(),
            'created_at' => fake()->dateTimeBetween('-24 hours'),
        ];
    }
}
