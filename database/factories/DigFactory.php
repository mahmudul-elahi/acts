<?php

namespace Database\Factories;

use App\Enums\DigType;
use App\Models\Dig;
use App\Models\DigLayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dig>
 */
class DigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Emotional Intelligence', 'Pattern Awareness', 'Self Discovery']),
            'type' => fake()->randomElement(DigType::cases()),
            'status' => true,
            'published_on' => null,
        ];
    }

    /**
     * Indicate that the dig is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
        ]);
    }

    /**
     * Schedule the dig for a given day (defaults to today).
     */
    public function scheduledFor(?string $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_on' => $date ?? now()->toDateString(),
        ]);
    }

    /**
     * Attach a set of sequentially positioned layers, each worth 20 XP.
     *
     * Mirrors the mockup's structure: odd layers are multiple-choice
     * ("The Question" / "The Experience") and even layers are journal entries
     * ("The Journal" / "The Reflection").
     */
    public function withLayers(int $count = 4): static
    {
        $titles = ['The Question', 'The Journal', 'The Experience', 'The Reflection'];

        return $this->afterCreating(function (Dig $dig) use ($count, $titles): void {
            for ($position = 1; $position <= $count; $position++) {
                $factory = DigLayer::factory()->for($dig);

                if ($position % 2 === 0) {
                    $factory = $factory->text();
                }

                $factory->create([
                    'position' => $position,
                    'title' => $titles[($position - 1) % count($titles)],
                    'xp' => 20,
                ]);
            }
        });
    }
}
