<?php

namespace Database\Factories;

use App\Models\JournalTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JournalTag>
 */
class JournalTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(fake()->numberBetween(1, 2), true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
