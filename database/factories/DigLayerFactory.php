<?php

namespace Database\Factories;

use App\Enums\DigAnswerType;
use App\Models\Dig;
use App\Models\DigLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DigLayer>
 */
class DigLayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dig_id' => Dig::factory(),
            'position' => 1,
            'title' => fake()->randomElement(['The Question', 'The Journal', 'The Experience', 'The Reflection']),
            'question' => fake()->sentence(),
            'answer_type' => DigAnswerType::Option,
            'xp' => 20,
            'include_other' => true,
            'options' => ['Fear', 'Sadness', 'Anger', 'Shame', 'Loneliness'],
            'placeholder' => null,
        ];
    }

    /**
     * Indicate that the layer collects a free-text / journal response.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes): array => [
            'answer_type' => DigAnswerType::Text,
            'include_other' => false,
            'options' => null,
            'placeholder' => 'Journal entry copies (if possible) to their actual journal...',
        ]);
    }
}
